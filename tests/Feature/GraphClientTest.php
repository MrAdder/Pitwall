<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Integrations\MicrosoftGraph\Authentication\AccessToken;
use App\Integrations\MicrosoftGraph\Authentication\TokenProvider;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphPermissionDenied;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphResourceNotFound;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphThrottled;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphUnavailable;
use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Graph is throttled, flaky and occasionally hostile. These tests pin down the
 * behaviour the rest of the application depends on: that transient failures are
 * retried, that Retry-After is obeyed, that pagination is followed, and that
 * every failure arrives as a typed exception a caller can act on.
 */
final class GraphClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Retry backoff is exercised without actually waiting.
        Sleep::fake();

        $this->app->bind(TokenProvider::class, fn () => new class implements TokenProvider
        {
            public function tokenFor(Tenant $tenant): AccessToken
            {
                return new AccessToken('test-token', CarbonImmutable::now()->addHour());
            }

            public function forget(Tenant $tenant): void {}
        });
    }

    #[Test]
    public function it_retries_transient_server_errors_and_then_succeeds(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::sequence()
                ->push(status: 503)
                ->push(status: 503)
                ->push(['value' => [['id' => 'user-1']]], 200),
        ]);

        $response = $this->client()->get('users');

        $this->assertSame([['id' => 'user-1']], $response->items());
        Http::assertSentCount(3);
    }

    #[Test]
    public function it_honours_retry_after_when_throttled(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::sequence()
                ->push(status: 429, headers: ['Retry-After' => '3'])
                ->push(['value' => []], 200),
        ]);

        $this->client()->get('users');

        // Microsoft's own wait wins over our backoff curve: guessing shorter is
        // how a tenant ends up throttled harder.
        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds === 3000.0, times: 1);
    }

    #[Test]
    public function it_gives_up_when_throttling_outlasts_the_retry_budget(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(status: 429, headers: ['Retry-After' => '1'])]);

        $this->expectException(GraphThrottled::class);

        $this->client()->get('users');
    }

    #[Test]
    public function it_does_not_block_on_an_unreasonable_retry_after(): void
    {
        // Microsoft occasionally asks for a very long wait. Holding a worker
        // for it is worse than requeuing the job.
        Http::fake(['graph.microsoft.com/*' => Http::response(status: 429, headers: ['Retry-After' => '900'])]);

        try {
            $this->client()->get('users');
            $this->fail('Expected GraphThrottled.');
        } catch (GraphThrottled $e) {
            $this->assertSame(900, $e->retryAfterSeconds());
            $this->assertTrue($e->isRetryable());
        }

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    #[Test]
    public function a_permission_failure_names_the_permission_that_is_missing(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'error' => ['code' => 'Authorization_RequestDenied', 'message' => 'Insufficient privileges.'],
            ], 403),
        ]);

        try {
            $this->client()->get('users', options: GraphRequestOptions::requiring('User.Read.All'));
            $this->fail('Expected GraphPermissionDenied.');
        } catch (GraphPermissionDenied $e) {
            $this->assertSame('User.Read.All', $e->requiredPermission());
            $this->assertStringContainsString('User.Read.All', $e->userMessage());
            $this->assertSame('Authorization_RequestDenied', $e->graphErrorCode());
        }
    }

    #[Test]
    public function a_missing_object_is_a_typed_not_found(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'error' => ['code' => 'Request_ResourceNotFound', 'message' => 'Not found.'],
            ], 404),
        ]);

        $this->expectException(GraphResourceNotFound::class);

        $this->client()->get('users/missing');
    }

    #[Test]
    public function a_persistent_server_error_becomes_a_retryable_unavailable(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(status: 500)]);

        try {
            $this->client()->get('users');
            $this->fail('Expected GraphUnavailable.');
        } catch (GraphUnavailable $e) {
            $this->assertTrue($e->isRetryable());
        }

        // Attempted the full retry budget before giving up.
        Http::assertSentCount((int) config('graph.retry.max_attempts'));
    }

    #[Test]
    public function it_re_authenticates_once_after_a_401(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::sequence()
                ->push(status: 401)
                ->push(['value' => [['id' => 'user-1']]], 200),
        ]);

        // A token can be revoked before it expires, so one silent retry with a
        // fresh token is worth making before declaring the tenant broken.
        $response = $this->client()->get('users');

        $this->assertSame([['id' => 'user-1']], $response->items());
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_follows_pagination_until_there_is_no_next_link(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::sequence()
                ->push([
                    'value' => [['id' => '1'], ['id' => '2']],
                    '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users?$skiptoken=abc',
                ], 200)
                ->push(['value' => [['id' => '3']]], 200),
        ]);

        $ids = array_column(iterator_to_array($this->client()->paginate('users')), 'id');

        $this->assertSame(['1', '2', '3'], $ids);
    }

    #[Test]
    public function a_delta_query_returns_the_link_for_the_next_run(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'value' => [['id' => '1']],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/users/delta?$deltatoken=xyz',
            ], 200),
        ]);

        $result = $this->client()->delta('users/delta');

        $this->assertCount(1, $result['items']);
        $this->assertStringContainsString('deltatoken', (string) $result['delta_link']);
    }

    #[Test]
    public function a_truncated_delta_sequence_returns_no_link_so_the_next_run_falls_back_to_a_full_sync(): void
    {
        // No deltaLink and no nextLink means the sequence ended without giving
        // us a resume point. Storing nothing forces a full enumeration next
        // time, which is slow but correct — the alternative silently drops
        // changes.
        Http::fake(['graph.microsoft.com/*' => Http::response(['value' => [['id' => '1']]], 200)]);

        $result = $this->client()->delta('users/delta');

        $this->assertNull($result['delta_link']);
    }

    #[Test]
    public function it_never_sends_the_token_anywhere_but_the_authorization_header(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(['value' => []], 200)]);

        $this->client()->get('users', ['$top' => 10]);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-token')
                && ! str_contains($request->url(), 'test-token');
        });
    }

    private function client(): GraphClient
    {
        return new GraphClient(
            Tenant::factory()->create(),
            app(TokenProvider::class),
            app(HttpFactory::class),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient;

use App\Integrations\MicrosoftGraph\Authentication\TokenProvider;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphAuthenticationFailed;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphException;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphPermissionDenied;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphRequestRejected;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphResourceNotFound;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphThrottled;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphUnavailable;
use App\Models\Tenant;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * The only place in the application that speaks HTTP to Microsoft Graph.
 *
 * One instance is bound to one tenant, so a caller cannot accidentally issue a
 * request against a directory other than the one it is working on. Instances
 * are built by {@see GraphClientFactory}.
 *
 * Responsibilities kept here rather than spread across callers:
 *
 *   - acquiring and attaching the tenant's access token
 *   - retrying transient failures with exponential backoff and jitter
 *   - honouring Retry-After when Microsoft throttles us
 *   - re-authenticating once on a 401, since tokens can be revoked early
 *   - translating HTTP failures into typed, explainable exceptions
 *   - following @odata.nextLink so callers never hand-roll pagination
 *   - logging what happened without ever logging a token or a payload
 */
final class GraphClient
{
    private string $version;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly TokenProvider $tokens,
        private readonly HttpFactory $http,
        ?string $version = null,
    ) {
        $this->version = $version ?? (string) config('graph.version', 'v1.0');
    }

    /**
     * A client pinned to the beta endpoint.
     *
     * Every use must be justified in docs/graph-permissions.md: beta is not
     * covered by Microsoft's support or deprecation guarantees, so anything
     * built on it can break without notice.
     */
    public function beta(): self
    {
        return new self($this->tenant, $this->tokens, $this->http, 'beta');
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = [], GraphRequestOptions $options = new GraphRequestOptions): GraphResponse
    {
        return $this->send('GET', $path, query: $query, options: $options);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(string $path, array $body = [], GraphRequestOptions $options = new GraphRequestOptions): GraphResponse
    {
        return $this->send('POST', $path, body: $body, options: $options);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function patch(string $path, array $body = [], GraphRequestOptions $options = new GraphRequestOptions): GraphResponse
    {
        return $this->send('PATCH', $path, body: $body, options: $options);
    }

    public function delete(string $path, GraphRequestOptions $options = new GraphRequestOptions): GraphResponse
    {
        return $this->send('DELETE', $path, options: $options);
    }

    /**
     * Walk every page of a collection, yielding one item at a time.
     *
     * A generator so a 50,000-user directory is never held in memory at once.
     * Callers that need the whole set can wrap it in iterator_to_array, and
     * take responsibility for the memory that implies.
     *
     * @param  array<string, mixed>  $query
     * @return Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, array $query = [], GraphRequestOptions $options = new GraphRequestOptions): Generator
    {
        $response = $this->get($path, $query, $options);
        $maxPages = (int) config('graph.max_pages', 5000);
        $page = 1;

        while (true) {
            // Yielded one at a time rather than with `yield from`, which would
            // reuse each page's array keys and let page two silently overwrite
            // page one for any caller that collects into an array.
            foreach ($response->items() as $item) {
                yield $item;
            }

            $next = $response->nextLink();

            if ($next === null) {
                return;
            }

            if (++$page > $maxPages) {
                // Rather than loop forever on an unexpectedly huge result set,
                // stop and make the truncation visible in the logs.
                Log::warning('Graph pagination stopped at the page limit.', [
                    'tenant_id' => $this->tenant->id,
                    'path' => $path,
                    'max_pages' => $maxPages,
                ]);

                return;
            }

            // nextLink is absolute and already carries the query, including
            // skiptoken. It must be used verbatim.
            $response = $this->send('GET', $next, options: $options, absolute: true);
        }
    }

    /**
     * Walk a delta collection, returning the items and the deltaLink to store
     * for the next incremental run.
     *
     * @param  array<string, mixed>  $query
     * @return array{items: array<int, array<string, mixed>>, delta_link: ?string}
     */
    public function delta(string $pathOrDeltaLink, array $query = [], GraphRequestOptions $options = new GraphRequestOptions): array
    {
        $isResume = Str::startsWith($pathOrDeltaLink, 'http');

        $response = $isResume
            ? $this->send('GET', $pathOrDeltaLink, options: $options, absolute: true)
            : $this->get($pathOrDeltaLink, $query, $options);

        $items = [];
        $maxPages = (int) config('graph.max_pages', 5000);
        $page = 1;

        while (true) {
            foreach ($response->items() as $item) {
                $items[] = $item;
            }

            if ($delta = $response->deltaLink()) {
                return ['items' => $items, 'delta_link' => $delta];
            }

            $next = $response->nextLink();

            if ($next === null || ++$page > $maxPages) {
                // No deltaLink means the sequence was truncated; returning null
                // makes the next run fall back to a full synchronisation rather
                // than silently skipping changes.
                return ['items' => $items, 'delta_link' => null];
            }

            $response = $this->send('GET', $next, options: $options, absolute: true);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     *
     * @throws GraphException
     */
    private function send(
        string $method,
        string $pathOrUrl,
        array $query = [],
        array $body = [],
        GraphRequestOptions $options = new GraphRequestOptions,
        bool $absolute = false,
    ): GraphResponse {
        $url = $absolute ? $pathOrUrl : $this->url($pathOrUrl);
        $logPath = $absolute ? $this->pathForLogging($pathOrUrl) : ltrim($pathOrUrl, '/');

        $maxAttempts = max(1, (int) config('graph.retry.max_attempts', 5));
        $clientRequestId = (string) Str::uuid();
        $startedAt = microtime(true);

        $attempt = 0;
        $reauthenticated = false;

        while (true) {
            $attempt++;

            try {
                $response = $this->execute($method, $url, $query, $body, $clientRequestId, $options);
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    $this->sleepFor($this->backoffMilliseconds($attempt));

                    continue;
                }

                throw new GraphUnavailable(
                    method: $method,
                    path: $logPath,
                    status: 0,
                    errorCode: 'connection_failed',
                    graphMessage: 'Could not reach Microsoft Graph.',
                    previous: $e,
                );
            }

            if ($response->successful()) {
                $this->logRequest($method, $logPath, $response->status(), $startedAt, $attempt, $response);

                return GraphResponse::fromHttpResponse($response);
            }

            $status = $response->status();

            // A token can be revoked or invalidated before it expires. Drop the
            // cached one and try once more; a second 401 is a real failure.
            if ($status === 401 && ! $reauthenticated) {
                $reauthenticated = true;
                $this->tokens->forget($this->tenant);

                continue;
            }

            if ($this->isRetryable($status) && $attempt < $maxAttempts) {
                $delayMs = $this->delayForRetry($response, $attempt);

                if ($delayMs !== null) {
                    $this->sleepFor($delayMs);

                    continue;
                }

                // Microsoft asked us to wait longer than a request should hold
                // a worker for. Fall through and let the caller requeue.
            }

            $this->logRequest($method, $logPath, $status, $startedAt, $attempt, $response);

            throw $this->exceptionFor($method, $logPath, $response, $options);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     *
     * @throws ConnectionException
     */
    private function execute(
        string $method,
        string $url,
        array $query,
        array $body,
        string $clientRequestId,
        GraphRequestOptions $options,
    ): Response {
        $token = $this->tokens->tokenFor($this->tenant);

        $request = $this->http
            ->withHeaders([
                'Authorization' => $token->authorizationHeader(),
                'Accept' => 'application/json',
                // Echoed back by Graph in responses and visible in Microsoft's
                // own telemetry, which makes support escalations tractable.
                'client-request-id' => $clientRequestId,
            ])
            ->connectTimeout((int) config('graph.http.connect_timeout', 10))
            ->timeout((int) config('graph.http.timeout', 30));

        if ($options->headers !== []) {
            $request = $request->withHeaders($options->headers);
        }

        // Advanced query capabilities ($count, $search, and $filter on some
        // properties) require this header plus $count=true.
        if ($options->consistencyLevelEventual) {
            $request = $request->withHeaders(['ConsistencyLevel' => 'eventual']);
        }

        return match ($method) {
            'GET' => $request->get($url, $query),
            'DELETE' => $request->delete($url),
            'POST' => $request->post($url, $body),
            'PATCH' => $request->patch($url, $body),
            default => throw new \InvalidArgumentException("Unsupported Graph method [{$method}]."),
        };
    }

    private function url(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('graph.base_url'), '/'),
            $this->version,
            ltrim($path, '/'),
        );
    }

    /**
     * Strip the host and query from an absolute URL before logging it. The
     * query of a nextLink contains a skiptoken, and a $filter can contain the
     * name or address of a real person.
     */
    private function pathForLogging(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?: $url);
    }

    private function isRetryable(int $status): bool
    {
        return in_array($status, (array) config('graph.retry.retryable_statuses', []), strict: true);
    }

    /**
     * How long to wait before the next attempt, or null when Microsoft's
     * requested wait is too long to block on.
     */
    private function delayForRetry(Response $response, int $attempt): ?int
    {
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            $seconds = (int) $retryAfter;
            $maximum = (int) config('graph.retry.max_retry_after_seconds', 120);

            if ($seconds > $maximum) {
                return null;
            }

            // Microsoft's own value wins over our backoff curve.
            return $seconds * 1000;
        }

        return $this->backoffMilliseconds($attempt);
    }

    /**
     * Exponential backoff with full jitter. The jitter matters: without it a
     * batch of sync jobs throttled together retries in lockstep and throttles
     * itself again.
     */
    private function backoffMilliseconds(int $attempt): int
    {
        $base = (int) config('graph.retry.base_delay_ms', 500);
        $max = (int) config('graph.retry.max_delay_ms', 60000);

        $ceiling = min($max, $base * (2 ** ($attempt - 1)));

        return random_int((int) ($ceiling / 2), $ceiling);
    }

    private function sleepFor(int $milliseconds): void
    {
        // Illuminate\Support\Sleep is fake-able, so tests exercise the retry
        // logic without actually waiting.
        Sleep::for($milliseconds)->milliseconds();
    }

    private function exceptionFor(string $method, string $path, Response $response, GraphRequestOptions $options): GraphException
    {
        $status = $response->status();
        $errorCode = $this->stringOrNull($response->json('error.code'));
        $message = $this->stringOrNull($response->json('error.message'));
        $requestId = $response->header('request-id') ?: null;

        return match (true) {
            $status === 401 => new GraphAuthenticationFailed($method, $path, $status, $errorCode, $message, $requestId),

            $status === 403 => new GraphPermissionDenied(
                $method, $path, $status, $errorCode, $message, $requestId,
                requiredPermission: $options->requiredPermission,
            ),

            $status === 404 => new GraphResourceNotFound($method, $path, $status, $errorCode, $message, $requestId),

            $status === 429 => new GraphThrottled(
                $method, $path, $status, $errorCode, $message, $requestId,
                retryAfterSeconds: is_numeric($response->header('Retry-After'))
                    ? (int) $response->header('Retry-After')
                    : null,
            ),

            $status >= 500 => new GraphUnavailable($method, $path, $status, $errorCode, $message, $requestId),

            default => new GraphRequestRejected($method, $path, $status, $errorCode, $message, $requestId),
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Records the shape of the call, never its contents. No token, no request
     * body, no response body.
     */
    private function logRequest(string $method, string $path, int $status, float $startedAt, int $attempts, Response $response): void
    {
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        $slowThreshold = (int) config('graph.logging.slow_request_ms', 5000);

        $isNoteworthy = $status >= 400
            || $attempts > 1
            || $durationMs >= $slowThreshold;

        if (! $isNoteworthy && ! config('graph.logging.enabled')) {
            return;
        }

        $context = [
            'tenant_id' => $this->tenant->id,
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
            'attempts' => $attempts,
            'graph_request_id' => $response->header('request-id') ?: null,
        ];

        $channel = Log::channel((string) config('graph.logging.channel', 'stack'));

        $status >= 400
            ? $channel->warning('Microsoft Graph request failed.', $context)
            : $channel->info('Microsoft Graph request.', $context);
    }
}

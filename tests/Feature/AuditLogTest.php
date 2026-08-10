<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\AuditResult;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The audit trail's value rests entirely on being unalterable. These tests
 * hold that line.
 */
final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_entry_cannot_be_modified_after_it_is_written(): void
    {
        [$tenant] = $this->tenantWithMember();

        $entry = $this->asTenant($tenant, fn () => app(AuditLogger::class)->log(
            new AuditEntry(action: AuditAction::UserDisabled, resourceType: 'user', resourceId: 'abc'),
        ));

        $this->expectException(RuntimeException::class);

        // Rewriting a failure as a success is precisely the tampering this
        // guard exists to stop.
        $this->asTenant($tenant, fn () => $entry->update(['result' => AuditResult::Failure]));
    }

    #[Test]
    public function an_entry_cannot_be_deleted(): void
    {
        [$tenant] = $this->tenantWithMember();

        $entry = $this->asTenant($tenant, fn () => app(AuditLogger::class)->log(
            new AuditEntry(action: AuditAction::UserDisabled),
        ));

        $this->expectException(RuntimeException::class);

        $this->asTenant($tenant, fn () => $entry->delete());
    }

    #[Test]
    public function a_failed_action_is_recorded_and_the_exception_still_propagates(): void
    {
        [$tenant] = $this->tenantWithMember();

        $this->asTenant($tenant, function (): void {
            try {
                app(AuditLogger::class)->around(
                    new AuditEntry(action: AuditAction::DeviceWiped, resourceLabel: 'LAPTOP-1'),
                    fn () => throw new RuntimeException('Graph said no'),
                );

                $this->fail('The exception should have propagated.');
            } catch (RuntimeException) {
                // Expected: auditing a failure must not swallow it.
            }

            $entry = AuditLog::firstOrFail();

            $this->assertSame(AuditResult::Failure, $entry->result);
            $this->assertSame('LAPTOP-1', $entry->resource_label);
            $this->assertStringContainsString('Graph said no', (string) $entry->error_message);
        });
    }

    #[Test]
    public function an_entry_records_the_actor_and_a_correlation_id(): void
    {
        [$tenant, $user] = $this->tenantWithMember();

        $this->actingAs($user);

        $entry = $this->asTenant($tenant, fn () => app(AuditLogger::class)->log(
            new AuditEntry(action: AuditAction::UserSessionsRevoked),
        ));

        $this->assertSame($user->id, $entry->actor_id);
        $this->assertSame($user->email, $entry->actor_email);
        $this->assertNotEmpty($entry->correlation_id);
    }

    #[Test]
    public function a_denial_is_recorded_rather_than_passing_silently(): void
    {
        [$tenant] = $this->tenantWithMember();

        $entry = $this->asTenant($tenant, fn () => app(AuditLogger::class)->denied(
            new AuditEntry(action: AuditAction::PermissionDenied, resourceId: 'devices.wipe'),
            'Role operator does not grant devices.wipe.',
        ));

        $this->assertSame(AuditResult::Denied, $entry->result);
    }
}

<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Entra;

use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Integrations\MicrosoftGraph\GraphPermission;
use Generator;

/**
 * Microsoft Graph `/users`.
 *
 * @see https://learn.microsoft.com/graph/api/resources/user
 */
final readonly class UsersResource
{
    /**
     * Properties fetched for the cached projection.
     *
     * Explicit rather than letting Graph choose: the default set omits some of
     * these, and requesting only what we store keeps response sizes down on
     * directories with tens of thousands of users.
     *
     * @var list<string>
     */
    public const SelectProperties = [
        'id',
        'userPrincipalName',
        'displayName',
        'givenName',
        'surname',
        'mail',
        'jobTitle',
        'department',
        'officeLocation',
        'mobilePhone',
        'employeeId',
        'usageLocation',
        'userType',
        'accountEnabled',
        'onPremisesSyncEnabled',
        'createdDateTime',
        'assignedLicenses',
    ];

    public function __construct(
        private GraphClient $client,
    ) {}

    /**
     * Every user in the directory.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function list(): Generator
    {
        return $this->client->paginate('users', [
            '$select' => implode(',', self::SelectProperties),
            '$top' => config('graph.page_size.users'),
        ], GraphRequestOptions::requiring(GraphPermission::UsersRead));
    }

    /**
     * Changes since the previous run.
     *
     * Pass the stored deltaLink to resume; pass null for the initial pass,
     * which enumerates the whole directory and returns a link for next time.
     *
     * Deleted users come back as entries carrying `@removed`.
     *
     * @return array{items: array<int, array<string, mixed>>, delta_link: ?string}
     *
     * @see https://learn.microsoft.com/graph/api/user-delta
     */
    public function delta(?string $deltaLink = null): array
    {
        if ($deltaLink !== null) {
            return $this->client->delta($deltaLink, options: GraphRequestOptions::requiring(GraphPermission::UsersRead));
        }

        return $this->client->delta('users/delta', [
            '$select' => implode(',', self::SelectProperties),
        ], GraphRequestOptions::requiring(GraphPermission::UsersRead));
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $userId): array
    {
        return $this->client->get("users/{$userId}", [
            '$select' => implode(',', self::SelectProperties),
        ], GraphRequestOptions::requiring(GraphPermission::UsersRead))->entity();
    }

    /**
     * Last interactive and non-interactive sign-in.
     *
     * Fetched separately from the main projection because signInActivity is
     * only returned when explicitly selected, requires AuditLog.Read.All on top
     * of User.Read.All, and is unavailable on tenants without an Entra ID P1
     * licence. Keeping it separate means the whole user sync does not fail on
     * tenants that cannot serve it.
     *
     * @return array<string, mixed>
     */
    public function signInActivity(string $userId): array
    {
        return $this->client->get("users/{$userId}", [
            '$select' => 'id,signInActivity',
        ], GraphRequestOptions::requiring(GraphPermission::AuditLogRead))->entity();
    }

    /**
     * Groups and directory roles the user belongs to.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function memberOf(string $userId): Generator
    {
        return $this->client->paginate("users/{$userId}/memberOf", [
            '$select' => 'id,displayName,description,groupTypes,securityEnabled,mailEnabled',
            '$top' => config('graph.page_size.groups'),
        ], GraphRequestOptions::requiring(GraphPermission::GroupMemberRead));
    }

    /**
     * Licences assigned to the user, with the SKU part numbers an
     * administrator recognises.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @see https://learn.microsoft.com/graph/api/user-list-licensedetails
     */
    public function licenseDetails(string $userId): Generator
    {
        return $this->client->paginate(
            "users/{$userId}/licenseDetails",
            options: GraphRequestOptions::requiring(GraphPermission::UsersRead),
        );
    }

    /**
     * Enable or disable the account.
     *
     * Disabling is the platform's supported alternative to deletion: it stops
     * sign-in immediately and is reversible, where deletion is not.
     *
     * Directory-synchronised accounts are mastered on-premises and Graph will
     * reject this, which surfaces as GraphRequestRejected carrying Microsoft's
     * own explanation.
     */
    public function setAccountEnabled(string $userId, bool $enabled): void
    {
        $this->client->patch("users/{$userId}", [
            'accountEnabled' => $enabled,
        ], GraphRequestOptions::requiring(GraphPermission::UsersReadWrite));
    }

    /**
     * Invalidate the user's refresh tokens and browser sessions.
     *
     * Sign-out is not instantaneous: existing access tokens remain valid until
     * they expire, typically within an hour. The UI says so rather than
     * implying the user is locked out at once.
     *
     * @see https://learn.microsoft.com/graph/api/user-revokesigninsessions
     */
    public function revokeSignInSessions(string $userId): void
    {
        $this->client->post(
            "users/{$userId}/revokeSignInSessions",
            options: GraphRequestOptions::requiring(GraphPermission::UserAuthenticationMethodReadWrite),
        );
    }
}

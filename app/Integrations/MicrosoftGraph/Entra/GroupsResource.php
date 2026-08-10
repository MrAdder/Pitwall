<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Entra;

use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Integrations\MicrosoftGraph\GraphPermission;
use Generator;

/**
 * Microsoft Graph `/groups`.
 *
 * @see https://learn.microsoft.com/graph/api/resources/group
 */
final readonly class GroupsResource
{
    /** @var list<string> */
    public const SelectProperties = [
        'id',
        'displayName',
        'description',
        'mail',
        'mailNickname',
        'mailEnabled',
        'securityEnabled',
        'groupTypes',
        'membershipRule',
        'membershipRuleProcessingState',
        'onPremisesSyncEnabled',
        'createdDateTime',
    ];

    public function __construct(
        private GraphClient $client,
    ) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function list(): Generator
    {
        return $this->client->paginate('groups', [
            '$select' => implode(',', self::SelectProperties),
            '$top' => config('graph.page_size.groups'),
        ], GraphRequestOptions::requiring(GraphPermission::GroupsRead));
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, delta_link: ?string}
     *
     * @see https://learn.microsoft.com/graph/api/group-delta
     */
    public function delta(?string $deltaLink = null): array
    {
        if ($deltaLink !== null) {
            return $this->client->delta($deltaLink, options: GraphRequestOptions::requiring(GraphPermission::GroupsRead));
        }

        return $this->client->delta('groups/delta', [
            '$select' => implode(',', self::SelectProperties),
        ], GraphRequestOptions::requiring(GraphPermission::GroupsRead));
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $groupId): array
    {
        return $this->client->get("groups/{$groupId}", [
            '$select' => implode(',', self::SelectProperties),
        ], GraphRequestOptions::requiring(GraphPermission::GroupsRead))->entity();
    }

    /**
     * User members of a group.
     *
     * The OData cast to microsoft.graph.user filters out nested groups, devices
     * and service principals server-side, so we do not pull objects we then
     * discard.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function userMembers(string $groupId): Generator
    {
        return $this->client->paginate("groups/{$groupId}/members/microsoft.graph.user", [
            '$select' => 'id,userPrincipalName,displayName,mail,accountEnabled',
            '$top' => config('graph.page_size.group_members'),
        ], GraphRequestOptions::requiring(GraphPermission::GroupMemberRead));
    }

    /**
     * Add a user to a group.
     *
     * Membership references are written to the `$ref` navigation endpoint with
     * an absolute directoryObjects URL, which is the shape Graph requires.
     *
     * @see https://learn.microsoft.com/graph/api/group-post-members
     */
    public function addMember(string $groupId, string $userId): void
    {
        $this->client->post("groups/{$groupId}/members/\$ref", [
            '@odata.id' => sprintf(
                '%s/%s/directoryObjects/%s',
                rtrim((string) config('graph.base_url'), '/'),
                config('graph.version'),
                $userId,
            ),
        ], GraphRequestOptions::requiring(GraphPermission::GroupMemberReadWrite));
    }

    /**
     * @see https://learn.microsoft.com/graph/api/group-delete-members
     */
    public function removeMember(string $groupId, string $userId): void
    {
        $this->client->delete(
            "groups/{$groupId}/members/{$userId}/\$ref",
            GraphRequestOptions::requiring(GraphPermission::GroupMemberReadWrite),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient;

/**
 * Per-request options for a Graph call.
 *
 * A value object rather than a bag of optional arguments, so adding an option
 * later does not change every call site.
 */
final readonly class GraphRequestOptions
{
    /**
     * @param  ?string  $requiredPermission  The Graph permission this call needs. Surfaced verbatim to the administrator on a 403, which turns "insufficient privileges" into an actionable instruction.
     * @param  bool  $consistencyLevelEventual  Send `ConsistencyLevel: eventual`, required by Graph for $count, $search and some $filter and $orderby combinations on directory objects.
     * @param  array<string, string>  $headers  Additional headers.
     */
    public function __construct(
        public ?string $requiredPermission = null,
        public bool $consistencyLevelEventual = false,
        public array $headers = [],
    ) {}

    public static function requiring(string $permission): self
    {
        return new self(requiredPermission: $permission);
    }

    public function withEventualConsistency(): self
    {
        return new self(
            requiredPermission: $this->requiredPermission,
            consistencyLevelEventual: true,
            headers: $this->headers,
        );
    }
}

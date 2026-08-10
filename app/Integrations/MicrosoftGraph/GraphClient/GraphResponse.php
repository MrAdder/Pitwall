<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

/**
 * A successful Graph response.
 *
 * Wraps the raw HTTP response so callers deal in Graph concepts (the value
 * collection, the next link, the delta link) rather than in JSON paths.
 */
final readonly class GraphResponse
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public int $status,
        public array $body,
        public ?string $requestId = null,
    ) {}

    public static function fromHttpResponse(Response $response): self
    {
        return new self(
            status: $response->status(),
            body: is_array($response->json()) ? $response->json() : [],
            requestId: $response->header('request-id') ?: null,
        );
    }

    /**
     * The `value` collection of a list response, or the whole body for a
     * single-entity response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        $value = $this->body['value'] ?? null;

        if (is_array($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return $this->body === [] ? [] : [$this->body];
    }

    /**
     * A single entity response body.
     *
     * @return array<string, mixed>
     */
    public function entity(): array
    {
        return $this->body;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->body, $key, $default);
    }

    /**
     * Link to the next page, present while more results exist.
     */
    public function nextLink(): ?string
    {
        $link = $this->body['@odata.nextLink'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }

    /**
     * Link to use for the next incremental sync. Only the final page of a
     * delta query carries one.
     */
    public function deltaLink(): ?string
    {
        $link = $this->body['@odata.deltaLink'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }

    /**
     * Total count, present only when the request asked for it with $count.
     */
    public function count(): ?int
    {
        $count = $this->body['@odata.count'] ?? null;

        return is_int($count) ? $count : null;
    }

    /**
     * Whether Graph accepted the request without returning content, as the
     * action endpoints (revokeSignInSessions, syncDevice, wipe) do.
     */
    public function isEmpty(): bool
    {
        return $this->body === [];
    }
}

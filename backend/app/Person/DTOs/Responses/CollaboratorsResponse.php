<?php

namespace App\Person\DTOs\Responses;

use JsonSerializable;

/** GET /api/people/{id}/collaborators. */
final readonly class CollaboratorsResponse implements JsonSerializable
{
    /**
     * @param  list<CollaboratorResponse>  $collaborators
     */
    public function __construct(public array $collaborators) {}

    /**
     * @return array{collaborators: list<CollaboratorResponse>}
     */
    public function jsonSerialize(): array
    {
        return ['collaborators' => $this->collaborators];
    }
}

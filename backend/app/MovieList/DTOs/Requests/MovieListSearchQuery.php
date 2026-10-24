<?php

namespace App\MovieList\DTOs\Requests;

final readonly class MovieListSearchQuery
{
    /**
     * Whose lists to show: the user's own, other members' public lists, or public lists by people they follow.
     *
     * @var list<string>
     */
    public const SCOPES = ['mine', 'public', 'following'];

    public function __construct(
        public string $scope = 'mine',
        public string $q = '',
    ) {}

    /**
     * @param  array{scope?: string, q?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['scope'] ?? 'mine', trim((string) ($validated['q'] ?? '')));
    }
}

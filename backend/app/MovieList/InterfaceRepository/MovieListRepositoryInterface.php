<?php

namespace App\MovieList\InterfaceRepository;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use Illuminate\Database\Eloquent\Collection;

interface MovieListRepositoryInterface
{
    /**
     * Lists in the query's scope, newest activity first, with their owner, films and film count.
     *
     * @return Collection<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer): Collection;

    /**
     * @param  array{user_id: int, title: string, description: string|null, is_public: bool}  $attributes
     */
    public function create(array $attributes): MovieList;

    /**
     * Saves the fields and bumps updated_at even when none of them changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(MovieList $list, array $attributes): void;

    public function delete(MovieList $list): void;

    /**
     * Makes the list hold exactly these films in this order, keeping notes of films that stay.
     *
     * @param  list<int>  $tmdbIds
     * @return list<int> films that were not in the list before
     */
    public function syncItems(MovieList $list, array $tmdbIds): array;

    /** Adds the film at the end of the list; false when it is already there. */
    public function addItem(MovieList $list, int $tmdbId): bool;

    /** Removes the film; false when it was not in the list. */
    public function removeItem(MovieList $list, int $tmdbId): bool;

    /** Loads the owner, the films and the film count. */
    public function loadDetail(MovieList $list): MovieList;
}

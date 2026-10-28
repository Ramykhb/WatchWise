<?php

namespace App\MovieList\Resources;

use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Support\MediaType;
use App\User\Resources\MemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A list card; with `items` loaded in full it also carries every film (List Detail, Edit dialog).
 *
 * @mixin MovieList
 */
class MovieListResource extends JsonResource
{
    public bool $withItems = false;

    public function withItems(): static
    {
        $this->withItems = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'films' => $this->items_count ?? $items->count(),
            'posters' => $items->take(5)->pluck('tmdb_id')->values(),
            // The same first five titles with their type, so a TV show's cover isn't mistaken for a movie's.
            'cover' => $items->take(5)->map(fn (MovieListItem $item) => [
                'tmdb_id' => $item->tmdb_id,
                'media_type' => $item->media_type ?? MediaType::MOVIE,
            ])->values(),
            'owner' => $this->relationLoaded('user') ? new MemberResource($this->user) : null,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            // Only when the request asked about a film (GET /lists?contains=).
            'has_movie' => $this->when(isset($this->has_movie), fn () => (bool) $this->has_movie),
            'items' => $this->when($this->withItems, fn () => $items->map(fn (MovieListItem $item) => [
                'tmdb_id' => $item->tmdb_id,
                'media_type' => $item->media_type ?? MediaType::MOVIE,
                'note' => $item->note,
                'added_at' => $item->added_at?->toJSON(),
            ])->values()),
        ];
    }
}

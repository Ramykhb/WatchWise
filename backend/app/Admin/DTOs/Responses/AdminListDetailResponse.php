<?php

namespace App\Admin\DTOs\Responses;

use App\Models\LocalMovie;
use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\Report;
use App\Support\MediaType;
use Illuminate\Support\Collection;
use JsonSerializable;

/** One list for staff: its row with the full description, its titles in order and the open reports against it. */
final readonly class AdminListDetailResponse implements JsonSerializable
{
    /**
     * @param  list<array{tmdb_id: int, media_type: string, title: ?string, poster_path: ?string, added_at: ?string}>  $items
     * @param  list<array{id: int, reason: string, details: ?string, reporter: array{id: int, name: string, username: ?string}|null, created_at: ?string}>  $reports
     */
    public function __construct(
        public AdminListResponse $list,
        public array $items,
        public array $reports,
    ) {}

    /**
     * Needs the list's `user`, the titles to show in `items` and `items_count`. `$films` is the local catalogue
     * keyed by TMDB id and `$reports` the open reports with their reporter loaded; a title that is a TV show or
     * not in the catalogue has no title or poster.
     *
     * @param  Collection<int, LocalMovie>  $films
     * @param  Collection<int, Report>  $reports
     */
    public static function fromModel(MovieList $list, Collection $films, Collection $reports): self
    {
        return new self(
            AdminListResponse::fromModel($list, $reports->count(), fullDescription: true),
            $list->items->map(function (MovieListItem $item) use ($films): array {
                $mediaType = $item->media_type ?? MediaType::MOVIE;
                $movie = $mediaType === MediaType::MOVIE ? $films->get($item->tmdb_id) : null;

                return [
                    'tmdb_id' => $item->tmdb_id,
                    'media_type' => $mediaType,
                    'title' => $movie?->title,
                    'poster_path' => $movie?->poster_path,
                    'added_at' => $item->added_at?->toJSON(),
                ];
            })->values()->all(),
            $reports->map(fn (Report $report): array => [
                'id' => $report->id,
                'reason' => $report->reason,
                'details' => $report->details,
                'reporter' => $report->reporter === null ? null : ['id' => $report->reporter->id, 'name' => $report->reporter->name, 'username' => $report->reporter->username],
                'created_at' => $report->created_at?->toJSON(),
            ])->values()->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...$this->list->jsonSerialize(), 'items' => $this->items, 'reports' => $this->reports];
    }
}

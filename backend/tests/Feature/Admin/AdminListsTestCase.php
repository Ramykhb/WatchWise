<?php

namespace Tests\Feature\Admin;

use App\Models\MovieList;
use App\Models\Report;
use App\Models\User;
use App\Report\Support\ReportTargetType;

/** Shared builders of the admin movie list suites. */
abstract class AdminListsTestCase extends AdminTestCase
{
    protected function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: int, 1?: string}>  $films  tmdb id and, for a TV show, `tv`
     */
    protected function listOf(User $owner, array $attributes = [], array $films = []): MovieList
    {
        $list = new MovieList;
        $list->forceFill([
            'user_id' => $owner->id,
            'title' => 'Best heists',
            'description' => null,
            'is_public' => true,
            ...$attributes,
        ])->save();

        foreach (array_values($films) as $position => $film) {
            $list->items()->create(['tmdb_id' => $film[0], 'media_type' => $film[1] ?? 'movie', 'position' => $position]);
        }

        return $list;
    }

    protected function reportOn(MovieList $list, ?User $reporter = null): Report
    {
        $report = Report::factory()->forTarget(ReportTargetType::MovieList, $list);

        return $reporter === null ? $report->create() : $report->create(['reporter_id' => $reporter->id]);
    }
}

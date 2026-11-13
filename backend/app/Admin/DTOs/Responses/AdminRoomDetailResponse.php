<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;
use JsonSerializable;

/** One room for staff: its list row, its members (at most 200, in the order they joined) and the open reports against the room itself. */
final readonly class AdminRoomDetailResponse implements JsonSerializable
{
    /**
     * @param  list<User>  $members  each with the `created_at` of its `room_members` row loaded as pivot
     * @param  list<Report>  $openReports  each with its `reporter` loaded
     */
    public function __construct(
        public AdminRoomResponse $room,
        public array $members,
        public array $openReports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->room->jsonSerialize(),
            'members' => array_map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'username' => $member->username,
                'joined_at' => Carbon::parse($member->pivot->created_at)->toJSON(),
            ], $this->members),
            'open_reports' => array_map(fn (Report $report): array => [
                'id' => $report->id,
                'reporter' => $report->reporter === null ? null : ['id' => $report->reporter->id, 'name' => $report->reporter->name, 'username' => $report->reporter->username],
                'reason' => $report->reason,
                'details' => $report->details,
                'created_at' => $report->created_at?->toJSON(),
            ], $this->openReports),
        ];
    }
}

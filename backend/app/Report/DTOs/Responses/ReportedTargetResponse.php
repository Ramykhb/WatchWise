<?php

namespace App\Report\DTOs\Responses;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * What a report is about, as staff see it. A target that no longer exists, or whose text a moderator or its
 * author already took away, comes back with `removed` true and no excerpt, so the report still shows in the queue.
 */
final readonly class ReportedTargetResponse implements JsonSerializable
{
    /** Longest excerpt of a message or review text. */
    public const EXCERPT_LENGTH = 200;

    /**
     * @param  array{id: int, name: string, username: ?string}|null  $owner  who made the content; a profile's owner is the member themself
     */
    public function __construct(
        public ReportTargetType $type,
        public int $id,
        public string $label,
        public ?string $excerpt,
        public ?array $owner,
        public bool $removed,
    ) {}

    /**
     * `$target` is the loaded row, or null when it is gone: then the label and excerpt kept with the report
     * (`$savedLabel`, `$savedExcerpt`) are shown, if there are any. Needs the row's author loaded: `user` on a
     * review or list, `user` and `room` on a message, `creator` on a room.
     */
    public static function describe(ReportTargetType $type, int $id, ?Model $target, ?string $savedLabel = null, ?string $savedExcerpt = null): self
    {
        if ($target === null) {
            return new self($type, $id, $savedLabel ?? 'Removed '.strtolower($type->label()), $savedExcerpt, null, true);
        }

        return match ($type) {
            ReportTargetType::Review => self::review($id, $target),
            ReportTargetType::Message => self::message($id, $target),
            ReportTargetType::MovieList => self::list($id, $target),
            ReportTargetType::Room => self::room($id, $target),
            ReportTargetType::Profile => self::profile($id, $target),
        };
    }

    private static function review(int $id, Review $review): self
    {
        $text = filled($review->content);

        return new self(ReportTargetType::Review, $id, self::by('Review', $review->user), $text ? self::excerpt($review->content) : null, self::owner($review->user), $review->removed_at !== null || ! $text);
    }

    private static function message(int $id, Message $message): self
    {
        return new self(ReportTargetType::Message, $id, self::by('Message', $message->user).' in '.$message->room->name, self::excerpt($message->content), self::owner($message->user), false);
    }

    private static function list(int $id, MovieList $list): self
    {
        return new self(ReportTargetType::MovieList, $id, self::by('List', $list->user), $list->title, self::owner($list->user), false);
    }

    private static function room(int $id, DiscussionRoom $room): self
    {
        return new self(ReportTargetType::Room, $id, self::by('Room', $room->creator), $room->name, self::owner($room->creator), false);
    }

    private static function profile(int $id, User $user): self
    {
        return new self(ReportTargetType::Profile, $id, 'Profile of '.self::handle($user), self::handle($user), self::owner($user), false);
    }

    private static function by(string $what, ?User $author): string
    {
        return $author === null ? $what : $what.' by '.self::handle($author);
    }

    private static function handle(User $user): string
    {
        return $user->username ?? $user->name;
    }

    private static function excerpt(string $text): string
    {
        return mb_substr($text, 0, self::EXCERPT_LENGTH);
    }

    /**
     * @return array{id: int, name: string, username: ?string}|null
     */
    private static function owner(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'username' => $user->username];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'label' => $this->label,
            'excerpt' => $this->excerpt,
            'owner' => $this->owner,
            'removed' => $this->removed,
        ];
    }
}

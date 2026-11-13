<?php

namespace App\Setting\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

/**
 * Every app-wide setting there is: the registry that declares each key's type, default and validation. Stored as
 * the string value in `app_settings.key`; never rename one, the row keeps it. A key that is not a case here cannot
 * be read or written, and a stored value that does not fit its key is read as the default.
 */
enum SettingKey: string
{
    case RegistrationOpen = 'registration_open';
    case ReviewsRequireApproval = 'reviews_require_approval';
    case ReviewAutoModeration = 'review_auto_moderation';
    case RoomsCreationEnabled = 'rooms_creation_enabled';
    case Announcement = 'announcement';

    /** Longest announcement message, in characters. */
    public const ANNOUNCEMENT_MESSAGE_MAX = 300;

    /** The earliest and latest year an announcement may expire in, counted in UTC. */
    private const EXPIRY_YEARS = [1970, 2100];

    /** The largest UTC offset there is, in minutes (+14:00). */
    private const MAX_OFFSET_MINUTES = 840;

    /** An ISO-8601 instant with a mandatory timezone: seconds and fraction optional; groups 8 to 10 are the offset. */
    private const ISO_INSTANT = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(?:(Z)|([+-])(\d{2}):(\d{2}))$/';

    /** What the value of a key looks like. */
    public function type(): SettingType
    {
        return match ($this) {
            self::Announcement => SettingType::Object,
            default => SettingType::Boolean,
        };
    }

    /**
     * The value used while nothing valid is stored: everything on as before the setting existed, announcement off.
     *
     * @return bool|array{enabled: bool, message: string, level: string, expires_at: string|null}
     */
    public function default(): bool|array
    {
        return match ($this) {
            self::RegistrationOpen, self::ReviewAutoModeration, self::RoomsCreationEnabled => true,
            self::ReviewsRequireApproval => false,
            self::Announcement => ['enabled' => false, 'message' => '', 'level' => AnnouncementLevel::Info->value, 'expires_at' => null],
        };
    }

    /**
     * The value a stored or submitted value stands for, or null when it does not fit this key. An announcement
     * comes back with every field present, the message cleaned (see `AnnouncementMessage`) and `expires_at` as a
     * UTC ISO-8601 instant, so two values that mean the same thing compare equal. An enabled announcement needs
     * a message, and the result always normalizes to itself.
     *
     * @return bool|array{enabled: bool, message: string, level: string, expires_at: string|null}|null
     */
    public function normalize(mixed $value): bool|array|null
    {
        return match ($this->type()) {
            SettingType::Boolean => is_bool($value) ? $value : null,
            SettingType::Object => $this->normalizeAnnouncement($value),
        };
    }

    /** The value to use for a stored one: the stored value when valid, else the default. */
    public function resolve(mixed $stored): bool|array
    {
        return $this->normalize($stored) ?? $this->default();
    }

    /**
     * A PATCH body with what the rules expect cleaned up: the announcement message loses its unwanted characters
     * (so a message of only control characters counts as missing). Everything else is returned as it came.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function cleanInput(array $body): array
    {
        $announcement = $body[self::Announcement->value] ?? null;

        if (is_array($announcement) && is_string($announcement['message'] ?? null)) {
            $body[self::Announcement->value]['message'] = AnnouncementMessage::clean($announcement['message']);
        }

        return $body;
    }

    /**
     * The validation rules of a PATCH body for this key, by attribute name. Every key is optional in the body.
     * The announcement needs `expires_at` in the future only while it is being enabled: a switched-off
     * announcement may keep an old date.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return match ($this->type()) {
            SettingType::Boolean => [$this->value => ['sometimes', 'required', 'boolean:strict']],
            SettingType::Object => [
                'announcement' => ['sometimes', 'required', 'array:enabled,message,level,expires_at'],
                'announcement.enabled' => ['required_with:announcement', 'boolean:strict'],
                'announcement.message' => [
                    'nullable', 'string', 'bail', 'max:'.self::ANNOUNCEMENT_MESSAGE_MAX, 'required_if:announcement.enabled,true',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_string($value) && ! AnnouncementMessage::isPlainText($value)) {
                            $fail('The :attribute must be plain text, without HTML tags.');
                        }
                    },
                ],
                'announcement.level' => ['required_with:announcement', Rule::enum(AnnouncementLevel::class)],
                'announcement.expires_at' => [
                    'nullable', 'string', 'bail',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if ($this->instant($value) === null) {
                            $fail('The :attribute must be an ISO-8601 date and time with a timezone, such as 2026-12-31T23:59:00Z, between '.self::EXPIRY_YEARS[0].' and '.self::EXPIRY_YEARS[1].' (UTC).');
                        }
                    },
                    Rule::when(fn (Fluent $input): bool => $input->get('announcement.enabled') === true, ['after:now']),
                ],
            ],
        };
    }

    /**
     * @return array{enabled: bool, message: string, level: string, expires_at: string|null}|null
     */
    private function normalizeAnnouncement(mixed $value): ?array
    {
        if (! is_array($value) || ! is_bool($value['enabled'] ?? null)) {
            return null;
        }

        $message = $value['message'] ?? '';
        $level = AnnouncementLevel::tryFrom((string) ($value['level'] ?? ''));
        $expiresAt = $value['expires_at'] ?? null;

        if (! is_string($message) || $level === null) {
            return null;
        }

        $message = AnnouncementMessage::clean($message);

        if (! AnnouncementMessage::isPlainText($message) || mb_strlen($message) > self::ANNOUNCEMENT_MESSAGE_MAX || ($value['enabled'] && $message === '')) {
            return null;
        }

        if ($expiresAt !== null) {
            $expiresAt = $this->instant($expiresAt);

            if ($expiresAt === null) {
                return null;
            }
        }

        return ['enabled' => $value['enabled'], 'message' => $message, 'level' => $level->value, 'expires_at' => $expiresAt];
    }

    /**
     * The UTC instant (`Y-m-d\TH:i:s\Z`) an ISO-8601 string with a timezone stands for, or null when it is not one
     * or is out of range (a month, day, hour, minute or second that does not exist, `T24:00:00`, an offset past
     * +/-14:00, or a UTC year outside `EXPIRY_YEARS`). A fraction of a second is dropped. The result parses back to itself.
     */
    private function instant(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::ISO_INSTANT, $value, $parts) !== 1) {
            return null;
        }

        [$year, $month, $day, $hour, $minute] = array_map('intval', array_slice($parts, 1, 5));
        $second = (int) $parts[6];
        $offset = ($parts[8] ?? '') === '' ? 0 : ($parts[8] === '-' ? -1 : 1) * ((int) $parts[9] * 60 + (int) $parts[10]);

        if (! checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59 || (int) ($parts[10] ?? 0) > 59 || abs($offset) > self::MAX_OFFSET_MINUTES) {
            return null;
        }

        $instant = CarbonImmutable::create($year, $month, $day, $hour, $minute, $second, 'UTC')->subMinutes($offset);

        if ($instant->year < self::EXPIRY_YEARS[0] || $instant->year > self::EXPIRY_YEARS[1]) {
            return null;
        }

        return $instant->format('Y-m-d\TH:i:s\Z');
    }
}

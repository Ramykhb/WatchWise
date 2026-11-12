<?php

namespace App\Setting\Support;

/**
 * The announcement message is plain text: it is shown to every visitor, so clients must render it as text and never
 * as HTML. Control characters and invisible text-direction overrides are removed, newlines (`\n`) are kept, and a
 * message that contains HTML tags is not accepted at all.
 */
final class AnnouncementMessage
{
    /** Control characters except the newline, plus the bidirectional embedding, override and isolate marks. */
    private const UNWANTED = '/[^\P{Cc}\n]|[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /** The text with `\r\n` and `\r` turned into `\n`, unwanted characters removed and the ends trimmed. */
    public static function clean(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);

        return trim(preg_replace(self::UNWANTED, '', $message) ?? '');
    }

    /** Whether the text holds no HTML tag. */
    public static function isPlainText(string $message): bool
    {
        return strip_tags($message) === $message;
    }
}

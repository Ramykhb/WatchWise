<?php

namespace App\Support;

/**
 * Values printed into the account emails. Display names are validated on input (`DisplayName`), but a name stored
 * earlier, or copied from Google, GitHub or Discord, may still hold such characters; in a plain-text mail a line
 * break in a name could fake extra lines, and a direction override could reorder the text around it.
 */
final class MailText
{
    /**
     * The value on one line: every run of the characters `DisplayName` refuses (control characters, separators,
     * direction overrides and marks...) or of invalid UTF-8 becomes one space. Emoji joiners are kept.
     */
    public static function line(?string $value): string
    {
        $value = mb_scrub((string) $value, 'UTF-8');

        return trim((string) preg_replace('/'.DisplayName::DISALLOWED.'+/u', ' ', $value));
    }
}

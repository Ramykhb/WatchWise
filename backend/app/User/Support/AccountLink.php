<?php

namespace App\User\Support;

use Illuminate\Http\Request;

/**
 * The signed links mailed for an email change or an account deletion, and the public frontend pages they land on.
 * The value is the `kind` the frontend reads: `/account-link/{kind}` asks for the click that confirms (a POST to
 * the same signed URL), `/account-link/result?kind=&outcome=` shows what happened. Both pages work without a
 * session, since confirming an email change signs every device out and the links open in any browser.
 */
enum AccountLink: string
{
    case EmailConfirm = 'email-confirm';
    case EmailAuthorize = 'email-authorize';
    case EmailCancel = 'email-cancel';
    case DeleteConfirm = 'delete-confirm';
    case DeleteCancel = 'delete-cancel';

    /** The link a route serves, by its name (the GET name or the `.submit` POST name); null for any other route. */
    public static function forRoute(?string $routeName): ?self
    {
        return match ($routeName) {
            'me.email.confirm', 'me.email.confirm.submit' => self::EmailConfirm,
            'me.email.authorize', 'me.email.authorize.submit' => self::EmailAuthorize,
            'me.email.cancel' => self::EmailCancel,
            'me.delete.confirm', 'me.delete.confirm.submit' => self::DeleteConfirm,
            'me.delete.cancel' => self::DeleteCancel,
            default => null,
        };
    }

    /**
     * The public page that asks for the confirming click, carrying the link's own `id`, `hash`, `expires` and
     * `signature` so it can POST the same signed URL.
     */
    public function confirmationPage(Request $request): string
    {
        return self::frontend('/account-link/'.$this->value, [
            'id' => $request->route('id'),
            'hash' => $request->route('hash'),
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
        ]);
    }

    /** The public page that shows the outcome (`cancelled`, `invalid`, `expired`, ...) of this link. */
    public function resultPage(string $outcome): string
    {
        return self::frontend('/account-link/result', ['kind' => $this->value, 'outcome' => $outcome]);
    }

    /** @param  array<string, mixed>  $query */
    private static function frontend(string $path, array $query): string
    {
        return rtrim((string) config('services.frontend_url'), '/').$path.'?'.http_build_query($query);
    }
}

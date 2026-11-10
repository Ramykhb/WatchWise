<?php

namespace Tests\Feature\User;

use Illuminate\Testing\TestResponse;

/**
 * Opens the signed email-change and account-deletion links the way a browser without a session does: a GET only
 * redirects to a public frontend page, and the confirming click on that page POSTs the same signed URL.
 */
trait OpensAccountLinks
{
    /** Opens the link (GET, no session) and asserts it only redirects to the public page asking to confirm. */
    private function assertOpensConfirmationPage(string $url, string $kind): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        [$id, $hash] = array_slice(explode('/', (string) parse_url($url, PHP_URL_PATH)), -2);

        $this->withoutHeader('Authorization')->get($url)->assertRedirect(
            config('services.frontend_url').'/account-link/'.$kind
            .'?id='.$id.'&hash='.$hash.'&expires='.$query['expires'].'&signature='.$query['signature'],
        );
    }

    /** The confirming click on the public page: POSTs the same signed URL, no session. */
    private function submitLink(string $url): TestResponse
    {
        return $this->withoutHeader('Authorization')->postJson($url);
    }

    /** Submits the link and asserts the handled outcome (always 200). */
    private function confirmLink(string $url, string $outcome): void
    {
        $this->submitLink($url)->assertOk()->assertExactJson(['outcome' => $outcome]);
    }

    /** Where a cancel link, or a GET with a bad signature, lands: the public result page. */
    private function resultPage(string $kind, string $outcome): string
    {
        return config('services.frontend_url').'/account-link/result?kind='.$kind.'&outcome='.$outcome;
    }
}

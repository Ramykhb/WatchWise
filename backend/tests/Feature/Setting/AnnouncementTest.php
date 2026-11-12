<?php

namespace Tests\Feature\Setting;

use Tests\Feature\Social\SocialTestCase;

/** The public announcement endpoint: signed-out access, expiry, throttling and a change showing on the next request. */
class AnnouncementTest extends SocialTestCase
{
    use ChangesSettings;

    public function test_there_is_no_announcement_until_an_admin_sets_one(): void
    {
        $this->getJson('/api/announcement')->assertOk()->assertExactJson(['announcement' => null]);
    }

    public function test_an_active_announcement_is_shown_to_signed_out_visitors(): void
    {
        $expires = now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z');
        $this->changeSettings(['announcement' => $this->announcement(message: 'Maintenance tonight.', level: 'critical', expiresAt: $expires)]);

        $this->getJson('/api/announcement')
            ->assertOk()
            ->assertExactJson(['announcement' => ['message' => 'Maintenance tonight.', 'level' => 'critical', 'expires_at' => $expires]]);
    }

    public function test_an_announcement_without_an_expiry_has_a_null_expires_at(): void
    {
        $this->changeSettings(['announcement' => $this->announcement()]);

        $this->getJson('/api/announcement')->assertOk()->assertJsonPath('announcement.expires_at', null);
    }

    public function test_a_disabled_announcement_is_null_and_keeps_no_text_in_the_response(): void
    {
        $this->changeSettings(['announcement' => $this->announcement(enabled: false, message: 'Hidden words')]);

        $this->getJson('/api/announcement')->assertOk()->assertExactJson(['announcement' => null]);
    }

    public function test_an_announcement_stops_showing_once_it_expires(): void
    {
        $this->changeSettings(['announcement' => $this->announcement(expiresAt: now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'))]);
        $this->getJson('/api/announcement')->assertOk()->assertJsonPath('announcement.message', 'Maintenance tonight.');

        $this->travel(2)->hours();

        $this->getJson('/api/announcement')->assertOk()->assertExactJson(['announcement' => null]);
    }

    public function test_a_change_shows_on_the_next_request_even_though_the_settings_are_cached(): void
    {
        $this->getJson('/api/announcement')->assertExactJson(['announcement' => null]);

        $this->changeSettings(['announcement' => $this->announcement(message: 'First')]);
        $this->getJson('/api/announcement')->assertJsonPath('announcement.message', 'First');

        $this->changeSettings(['announcement' => $this->announcement(message: 'Second')]);
        $this->getJson('/api/announcement')->assertJsonPath('announcement.message', 'Second');

        $this->changeSettings(['announcement' => $this->announcement(enabled: false)]);
        $this->getJson('/api/announcement')->assertExactJson(['announcement' => null]);
    }

    public function test_the_endpoint_allows_60_requests_a_minute_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/announcement')->assertOk();
        }

        $this->getJson('/api/announcement')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/announcement')->assertOk();
    }
}

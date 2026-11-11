<?php

namespace Tests\Feature\Admin;

class AdminRateLimitTest extends AdminTestCase
{
    public function test_admin_routes_allow_120_requests_a_minute_per_staff_member(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        for ($i = 0; $i < 120; $i++) {
            $this->as($admin)->getJson('/api/admin/users')->assertOk();
        }

        $this->as($admin)->getJson('/api/admin/users')->assertStatus(429);
        $this->as($admin)->getJson('/api/admin/reviews')->assertStatus(429);
        $this->as($other)->getJson('/api/admin/users')->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_calendar_routes_require_coach_role(): void
    {
        $this->get(route('settings.google-calendar.redirect'))
            ->assertRedirect(route('login'));

        $this->get(route('settings.google-calendar.callback'))
            ->assertRedirect(route('login'));

        $this->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('login'));
    }

    public function test_google_calendar_routes_are_forbidden_for_non_coach_users(): void
    {
        $users = [
            User::factory()->student()->create(),
            User::factory()->admin()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user);

            $this->get(route('settings.google-calendar.redirect'))
                ->assertForbidden();

            $this->get(route('settings.google-calendar.callback'))
                ->assertForbidden();

            $this->delete(route('settings.google-calendar.destroy'))
                ->assertForbidden();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingReminderWindow;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_eve_reminders(): void
    {
        // / Arrange
        Mail::fake();
        $this->travelTo(now()->setTime(18, 0));

        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'eve',
        ])->assertExitCode(0);

        // Assert
        $notificationCount = DatabaseNotification::query()
            ->where('type', MeetingReminderNotification::class)
            ->where('data->meeting_id', $meeting->id)
            ->where('data->window', MeetingReminderWindow::Eve->value)
            ->count();

        $this->assertSame(2, $notificationCount);
    }

    public function test_command_sends_one_hour_before_reminders(): void
    {
        // / Arrange
        Mail::fake();
        $this->travelTo(now()->setTime(18, 0));

        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create([
            'scheduled_at' => now()->addHour(),
        ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertExitCode(0);

        // Assert
        $notificationCount = DatabaseNotification::query()
            ->where('type', MeetingReminderNotification::class)
            ->where('data->meeting_id', $meeting->id)
            ->where('data->window', MeetingReminderWindow::OneHourBefore->value)
            ->count();

        $this->assertSame(2, $notificationCount);
    }

    public function test_command_fails_when_window_option_is_missing(): void
    {
        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(1);
    }

    public function test_command_fails_when_window_option_is_invalid(): void
    {
        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'invalid',
        ])->assertExitCode(1);

    }
}

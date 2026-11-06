<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_filter_returns_only_past_meetings(): void
    {
        // Arrange
        $auth = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($auth)->create([
            'scheduled_at' => now()->subDay(),
        ]);

        Meeting::factory()->reserved()->inFuture()->forCoach($coach)->forStudent($auth)->create();

        // Act
        $result = app(IndexAction::class)($auth, 'past');

        // Assert
        $this->assertSame(
            [$pastMeeting->id],
            $result['meetings']->getCollection()->pluck('id')->all(),
            '過去の面談だけが取得されるはず',
        );
    }

    public function test_all_filter_returns_past_and_upcoming_meetings(): void
    {
        // Arrange
        $auth = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($auth)->create([
            'scheduled_at' => now()->subDay(),
        ]);
        $upcomingMeeting = Meeting::factory()->reserved()->inFuture()->forCoach($coach)->forStudent($auth)->create();

        // Act
        $result = app(IndexAction::class)($auth, 'all');

        // Assert
        $this->assertSame(
            [$upcomingMeeting->id, $pastMeeting->id],
            $result['meetings']->getCollection()->pluck('id')->all(),
            '過去・未来の面談が開催日時の降順で取得されるはず',
        );
    }

    public function test_upcoming_filter_returns_only_upcoming_meetings(): void
    {
        // Arrange
        $auth = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        Meeting::factory()->completed()->forCoach($coach)->forStudent($auth)->create([
            'scheduled_at' => now()->subDay(),
        ]);
        $upcomingMeeting = Meeting::factory()->reserved()->inFuture()->forCoach($coach)->forStudent($auth)->create();

        // Act
        $result = app(IndexAction::class)($auth, 'upcoming');

        // Assert
        $this->assertSame(
            [$upcomingMeeting->id],
            $result['meetings']->getCollection()->pluck('id')->all(),
            '未来の面談だけが取得されるはず',
        );
    }

    public function test_returns_only_authenticated_students_meetings(): void
    {
        // Arrange
        $auth = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($auth)->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create();

        // Act
        $result = app(IndexAction::class)($auth, 'all');

        // Assert
        $this->assertSame(
            [$meeting->id],
            $result['meetings']->getCollection()->pluck('id')->all(),
            '本人の面談だけが取得されるはず',
        );
    }

    public function test_returns_meeting_remaining_quota(): void
    {
        // Arrange
        $auth = User::factory()->student()->inProgress()->create(
            ['max_meetings' => 4],
        );
        MeetingQuotaTransaction::factory()->consumed()->state(['user_id' => $auth->id])->create();

        // Act
        $result = app(IndexAction::class)($auth);

        // Assert
        $this->assertSame(
            3,
            $result['meetingRemaining'],
            '本人の残面談回数が取得されるはず',
        );
    }
}

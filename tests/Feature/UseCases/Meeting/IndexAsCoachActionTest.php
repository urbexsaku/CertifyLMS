<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_meetings_are_ordered_by_scheduled_at_ascending(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $nextMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay(),
        ]);

        $followingMeeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2),
        ]);

        // Act
        $result = app(IndexAsCoachAction::class)($coach, 'upcoming');

        // Assert
        $this->assertSame(
            [$nextMeeting->id, $followingMeeting->id],
            $result->getCollection()->pluck('id')->all(),
            '未来の面談が開催日時の昇順で取得されるはず',
        );
    }

    public function test_past_filter_returns_only_past_meetings_in_descending_order(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $previousMeeting = Meeting::factory()->completed()->forCoach($coach)->create([
            'scheduled_at' => now()->subDay(),
        ]);

        $precedingMeeting = Meeting::factory()->completed()->forCoach($coach)->create([
            'scheduled_at' => now()->subDays(2),
        ]);

        // Act
        $result = app(IndexAsCoachAction::class)($coach, 'past');

        // Assert
        $this->assertSame(
            [$previousMeeting->id, $precedingMeeting->id],
            $result->getCollection()->pluck('id')->all(),
            '過去の面談が開催日時の降順で取得されるはず',
        );
    }

    public function test_all_filter_returns_all_meetings_in_descending_order(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $pastMeeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDay(),
        ]);
        $upcomingMeeting = Meeting::factory()->reserved()->inFuture()->forCoach($coach)->forStudent($student)->create();

        // Act
        $result = app(IndexAsCoachAction::class)($coach, 'all');

        // Assert
        $this->assertSame(
            [$upcomingMeeting->id, $pastMeeting->id],
            $result->getCollection()->pluck('id')->all(),
            '過去・未来の面談が開催日時の降順で取得されるはず',
        );
    }

    public function test_student_filter_returns_only_selected_students_meetings(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create();

        // Act
        $result = app(IndexAsCoachAction::class)(
            auth: $coach,
            filter: 'all',
            studentId: (string) $student->id,
        );

        // Assert
        $this->assertSame(
            [$meeting->id],
            $result->getCollection()->pluck('id')->all(),
            '指定した受講生の面談だけが取得されるはず',
        );
    }

    public function test_enrollment_filter_returns_only_selected_enrollments_meetings(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();
        $otherEnrollment = Enrollment::factory()->learning()->for($student, 'user')->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($otherEnrollment)->create();

        // Act
        $result = app(IndexAsCoachAction::class)(
            auth: $coach,
            filter: 'all',
            enrollmentId: (string) $enrollment->id,
        );

        // Assert
        $this->assertSame(
            [$meeting->id],
            $result->getCollection()->pluck('id')->all(),
            '指定した受講登録の面談だけが取得されるはず',
        );
    }

    public function test_returns_only_meetings_assigned_to_authenticated_coach(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create();

        // Act
        $result = app(IndexAsCoachAction::class)($coach, 'all');

        // Assert
        $this->assertSame(
            [$meeting->id],
            $result->getCollection()->pluck('id')->all(),
            '担当コーチ本人の面談だけが取得されるはず',
        );
    }
}

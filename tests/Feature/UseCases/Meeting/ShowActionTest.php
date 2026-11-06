<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_required_relationships(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->for($cert)->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create();
        MeetingMemo::factory()->forMeeting($meeting)->create();

        // Act
        $result = app(ShowAction::class)($meeting);

        // Assert
        $this->assertTrue(
            $result->relationLoaded('enrollment'),
            'ShowAction は受講登録を eager load するはず',
        );

        $this->assertTrue(
            $result->enrollment->relationLoaded('certification'),
            'ShowAction は受講登録の資格情報を eager load するはず',
        );

        $this->assertTrue(
            $result->relationLoaded('coach'),
            'ShowAction はコーチを eager load するはず',
        );

        $this->assertTrue(
            $result->relationLoaded('student'),
            'ShowAction は受講生を eager load するはず',
        );

        $this->assertTrue(
            $result->relationLoaded('canceledBy'),
            'ShowAction はキャンセル者を eager load するはず',
        );

        $this->assertTrue(
            $result->relationLoaded('meetingMemo'),
            'ShowAction は面談メモを eager load するはず',
        );
    }
}

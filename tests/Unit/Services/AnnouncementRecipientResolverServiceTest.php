<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AnnouncementRecipientResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementRecipientResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_returns_only_in_progress_students(): void
    {
        // Arrange
        $inProgressStudent = User::factory()->student()->inProgress()->create();
        User::factory()->student()->graduated()->create();
        User::factory()->student()->withdrawn()->create();
        User::factory()->student()->invited()->create();
        User::factory()->coach()->inProgress()->create();
        User::factory()->admin()->inProgress()->create();

        // Act
        $results = app(AnnouncementRecipientResolverService::class)
            ->resolve(['target_type' => AnnouncementTargetType::AllStudents->value]);

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($inProgressStudent));
    }

    public function test_certification_target_returns_only_students_learning_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        Enrollment::factory()->learning()->for($student)->for($cert)->create();
        Enrollment::factory()->passed()->for($cert)->create();
        Enrollment::factory()->failed()->for($cert)->create();
        Enrollment::factory()->learning()->for($otherCert)->create();

        // Act
        $results = app(AnnouncementRecipientResolverService::class)
            ->resolve(['target_type' => AnnouncementTargetType::Certification->value, 'target_certification_id' => $cert->id]);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($student));
    }

    public function test_user_target_returns_only_specified_in_progress_student(): void
    {
        // Arrange
        $inProgressStudent = User::factory()->student()->inProgress()->create();
        User::factory()->student()->graduated()->create();
        User::factory()->student()->withdrawn()->create();
        User::factory()->coach()->inProgress()->create();
        User::factory()->admin()->inProgress()->create();

        // Act
        $results = app(AnnouncementRecipientResolverService::class)
            ->resolve(['target_type' => AnnouncementTargetType::User->value, 'target_user_id' => $inProgressStudent->id]);

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($inProgressStudent));
    }
}

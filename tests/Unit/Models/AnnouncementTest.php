<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $announcement = Announcement::factory()->create([
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);

        // Act
        $parent = $announcement->targetCertification;

        // Assert
        $this->assertTrue($parent->is($cert));
    }

    public function test_target_user_relation_returns_target_user(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create([
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $user->id,
        ]);

        // Act
        $parent = $announcement->targetUser;

        // Assert
        $this->assertTrue($parent->is($user));
    }

    public function test_created_by_relation_returns_creator(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by_user_id' => $admin->id]);

        // Act
        $creator = $announcement->createdBy;

        // Assert
        $this->assertTrue($creator->is($admin));
    }

    public function test_target_type_cast_converts_to_enum(): void
    {
        // Arrange
        $announcement = Announcement::factory()->create();

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(AnnouncementTargetType::class, $fresh->target_type);
        $this->assertSame(AnnouncementTargetType::AllStudents, $fresh->target_type);
    }

    public function test_dispatched_at_cast_returns_carbon_datetime(): void
    {
        // Arrange
        $announcement = Announcement::factory()->create(['dispatched_at' => now()]);

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->dispatched_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoal モデルのリレーションと属性キャストを検証する Unit テスト。
 * 親 Enrollment とのリレーション、および target_date・achieved_at の Carbon キャストを確認する。
 */
class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_parent_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $parent = $goal->enrollment;

        // Assert
        $this->assertTrue($parent->is($enrollment));
    }

    public function test_target_date_cast_returns_carbon_date(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->create([
            'target_date' => '2026-12-01',
        ]);

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->target_date);
        $this->assertSame('2026-12-01', $fresh->target_date->toDateString());
    }

    public function test_achieved_at_cast_returns_carbon_datetime(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->create([
            'achieved_at' => '2026-12-01',
        ]);

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->achieved_at, 'achieved_at は Carbon datetime にキャストされるはず');
    }
}

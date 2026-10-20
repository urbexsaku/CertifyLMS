<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\Enrollment\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment 詳細取得 Action `ShowAction` の eager load と目標取得を検証する Feature テスト。
 * certification の eager load、目標の取得範囲、達成状態・期日・作成日時に基づく表示順を確認する。
 */
class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_certification_for_detail_view(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertTrue(
            $result->relationLoaded('certification'),
            'ShowAction は詳細表示用に certification を eager load するはず',
        );
    }

    public function test_loads_goals_with_unachieved_goals_first(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '未達成の目標',
            'achieved_at' => null,
        ]);
        EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '達成済みの目標',
            'achieved_at' => '2026-10-21 12:00:00',
        ]);

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertTrue(
            $result->relationLoaded('goals'),
            'ShowAction は目標を eager load するはず',
        );

        $this->assertSame(
            ['未達成の目標', '達成済みの目標'],
            $result->goals->pluck('title')->all(),
            '未達成の目標が達成済みの目標より先に表示されるはず',
        );
    }

    public function test_orders_goals_by_target_date_then_created_at(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();

        $data = [
            ['未達成_期日遅い', '2026-11-01', null, '10:00:00'],
            ['未達成_同一期日_古い', '2026-10-01', null, '09:00:00'],
            ['未達成_同一期日_新しい', '2026-10-01', null, '11:00:00'],
            ['未達成_期日なし', null, null, '12:00:00'],
            ['達成済み_期日遅い', '2026-11-01', '2026-10-21 12:00:00', '10:00:00'],
            ['達成済み_同一期日_古い', '2026-10-01', '2026-10-21 12:00:00', '09:00:00'],
            ['達成済み_同一期日_新しい', '2026-10-01', '2026-10-21 12:00:00', '11:00:00'],
            ['達成済み_期日なし', null, '2026-10-21 12:00:00', '12:00:00'],
        ];

        foreach ($data as [$title, $targetDate, $achievedAt, $time]) {
            EnrollmentGoal::factory()
                ->forEnrollment($enrollment)
                ->create([
                    'title' => $title,
                    'target_date' => $targetDate,
                    'achieved_at' => $achievedAt,
                    'created_at' => "2026-10-01 {$time}",
                ]);
        }

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertSame(
            [
                '未達成_同一期日_新しい',
                '未達成_同一期日_古い',
                '未達成_期日遅い',
                '未達成_期日なし',
                '達成済み_同一期日_新しい',
                '達成済み_同一期日_古い',
                '達成済み_期日遅い',
                '達成済み_期日なし',
            ],
            $result->goals->pluck('title')->all(),
        );
    }

    public function test_loads_only_goals_for_the_given_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '対象の目標',
        ]);
        EnrollmentGoal::factory()->forEnrollment($otherEnrollment)->create([
            'title' => '他の目標',
        ]);

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertSame(
            ['対象の目標'],
            $result->goals->pluck('title')->all(),
            '指定した受講登録の目標だけが取得されるはず',
        );
    }
}

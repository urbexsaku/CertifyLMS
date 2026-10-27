<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use App\Policies\EnrollmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicy の判定を検証する Unit テスト。
 * view: 親 Enrollment の view 判定への委譲を確認する。
 * create / update / delete / markAchieved / unmarkAchieved:
 * 本人のみ許可し、他受講生・コーチ・管理者は拒否することを確認する。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_delegates_to_enrollment_view_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();

        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $enrollmentPolicy = new EnrollmentPolicy;
        $goalPolicy = new EnrollmentGoalPolicy;

        $this->assertSame(
            $enrollmentPolicy->view($student, $enrollment),
            $goalPolicy->view($student, $goal),
            '本人の view 判定は親 Enrollment の view 判定と一致するはず'
        );
        $this->assertTrue($goalPolicy->view($student, $goal));

        $this->assertSame(
            $enrollmentPolicy->view($coach, $enrollment),
            $goalPolicy->view($coach, $goal),
            '担当コーチの view 判定は親 Enrollment の view 判定と一致するはず'
        );
        $this->assertTrue($goalPolicy->view($coach, $goal));

        $this->assertSame(
            $enrollmentPolicy->view($admin, $enrollment),
            $goalPolicy->view($admin, $goal),
            '管理者の view 判定は親 Enrollment の view 判定と一致するはず'
        );
        $this->assertTrue($goalPolicy->view($admin, $goal));

        $this->assertSame(
            $enrollmentPolicy->view($otherStudent, $enrollment),
            $goalPolicy->view($otherStudent, $goal),
            '他受講生の view 判定は親 Enrollment の view 判定と一致するはず'
        );
        $this->assertFalse($goalPolicy->view($otherStudent, $goal));
    }

    public function test_create_allows_only_owner_student(): void
    {
        $scenario = $this->buildScenario();
        $policy = new EnrollmentGoalPolicy;
        $enrollment = $scenario['goal']->enrollment;

        $this->assertTrue($policy->create($scenario['user'], $scenario['goal']->enrollment));

        $otherStudent = User::factory()->student()->create();
        $this->assertFalse($policy->create($otherStudent, $enrollment), '他受講生は作成不可');

        $coachScenario = $this->buildScenario(userRole: UserRole::Coach);
        $this->assertFalse($policy->create($coachScenario['user'], $enrollment), 'コーチは作成不可');

        $adminScenario = $this->buildScenario(userRole: UserRole::Admin);
        $this->assertFalse($policy->create($adminScenario['user'], $enrollment), '管理者は作成不可');
    }

    public function test_update_allows_only_owner_student(): void
    {
        $scenario = $this->buildScenario();
        $policy = new EnrollmentGoalPolicy;
        $goal = $scenario['goal'];

        $this->assertTrue($policy->update($scenario['user'], $scenario['goal']));

        $otherStudent = User::factory()->student()->create();
        $this->assertFalse($policy->update($otherStudent, $goal), '他受講生は更新不可');

        $coachScenario = $this->buildScenario(userRole: UserRole::Coach);
        $this->assertFalse($policy->update($coachScenario['user'], $scenario['goal']), 'コーチは更新不可');

        $adminScenario = $this->buildScenario(userRole: UserRole::Admin);
        $this->assertFalse($policy->update($adminScenario['user'], $scenario['goal']), '管理者は更新不可');
    }

    public function test_delete_allows_only_owner_student(): void
    {
        $scenario = $this->buildScenario();
        $policy = new EnrollmentGoalPolicy;
        $goal = $scenario['goal'];

        $this->assertTrue($policy->delete($scenario['user'], $scenario['goal']));

        $otherStudent = User::factory()->student()->create();
        $this->assertFalse($policy->delete($otherStudent, $goal), '他受講生は削除不可');

        $coachScenario = $this->buildScenario(userRole: UserRole::Coach);
        $this->assertFalse($policy->delete($coachScenario['user'], $scenario['goal']), 'コーチは削除不可');

        $adminScenario = $this->buildScenario(userRole: UserRole::Admin);
        $this->assertFalse($policy->delete($adminScenario['user'], $scenario['goal']), '管理者は削除不可');
    }

    public function test_mark_achieved_allows_only_owner_student(): void
    {
        $scenario = $this->buildScenario();
        $policy = new EnrollmentGoalPolicy;
        $goal = $scenario['goal'];

        $this->assertTrue($policy->markAchieved($scenario['user'], $scenario['goal']));

        $otherStudent = User::factory()->student()->create();
        $this->assertFalse($policy->markAchieved($otherStudent, $goal), '他受講生は達成状態に変更不可');

        $coachScenario = $this->buildScenario(userRole: UserRole::Coach);
        $this->assertFalse($policy->markAchieved($coachScenario['user'], $scenario['goal']), 'コーチは達成状態に変更不可');

        $adminScenario = $this->buildScenario(userRole: UserRole::Admin);
        $this->assertFalse($policy->markAchieved($adminScenario['user'], $scenario['goal']), '管理者は達成状態に変更不可');
    }

    public function test_unmark_achieved_allows_only_owner_student(): void
    {
        $scenario = $this->buildScenario();
        $policy = new EnrollmentGoalPolicy;
        $goal = $scenario['goal'];

        $this->assertTrue($policy->unmarkAchieved($scenario['user'], $scenario['goal']));

        $otherStudent = User::factory()->student()->create();
        $this->assertFalse($policy->unmarkAchieved($otherStudent, $goal), '他受講生は未達成状態に変更不可');

        $coachScenario = $this->buildScenario(userRole: UserRole::Coach);
        $this->assertFalse($policy->unmarkAchieved($coachScenario['user'], $scenario['goal']), 'コーチは未達成状態に変更不可');

        $adminScenario = $this->buildScenario(userRole: UserRole::Admin);
        $this->assertFalse($policy->unmarkAchieved($adminScenario['user'], $scenario['goal']), '管理者は未達成状態に変更不可');
    }

    /**
     * @return array{user: User, goal: EnrollmentGoal}
     */
    private function buildScenario(
        UserRole $userRole = UserRole::Student,
        ?User $goalOwner = null,
    ): array {
        $user = User::factory()->create([
            'role' => $userRole,
        ]);

        $goalOwner ??= $user;

        $enrollment = Enrollment::factory()->for($goalOwner)->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        return [
            'user' => $user,
            'goal' => $goal,
        ];
    }
}

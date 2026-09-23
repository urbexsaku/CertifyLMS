<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * QaThreadPolicy の ability × Role × 本人性のマトリクス検証。
 * viewAny / view (admin は全資格ステータス、student は公開済みのみ、coach は担当資格の公開済みのみ)、
 * create (student のみ)、update (student の自身のスレッドのみ)、
 * delete (student は自身のスレッドのみ可、admin は可、coach は不可)、
 * resolve / unresolve (student の自身のスレッドのみ) を網羅する。
 *
 * create における資格の存在・公開状態の判定は Policy ではなく StoreRequest の責務とし、
 * Policy の create ではユーザーのロールのみを検証する。
 */
class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('viewAnyMatrix')]
    public function test_view_any_returns_expected_for_role(string $actingRole, bool $expected): void
    {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $policy = new QaThreadPolicy;

        // Act
        $result = $policy->viewAny($actor);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} の viewAny は ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    #[DataProvider('viewMatrix')]
    public function test_view_returns_expected_for_role_and_thread(
        string $actingRole,
        string $certStatus,
        bool $assigned,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $cert = Certification::factory()->create(['status' => CertificationStatus::from($certStatus)]);
        $thread = QaThread::factory()->for($cert)->create();

        if ($assigned && $actingRole === 'coach') {
            $admin = User::factory()->admin()->create();
            CertificationCoachAssignment::create([
                'id' => (string) Str::ulid(),
                'certification_id' => $cert->id,
                'user_id' => $actor->id,
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]);
            $cert->load('coaches');
        }
        $policy = new QaThreadPolicy;

        // Act
        $result = $policy->view($actor, $thread);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} の {$certStatus}・".($actingRole === 'coach'
                ? ($assigned ? '担当' : '未担当')
                : 'スレッド'
            ).'の view は '.($expected ? 'true' : 'false').' を返すはず',
        );
    }

    public function test_create_allowed_for_student(): void
    {
        $scenario = $this->buildScenario();

        $this->assertTrue(app(QaThreadPolicy::class)->create($scenario['user']));
    }

    public function test_create_denied_for_non_student(): void
    {
        $coachScenario = $this->buildScenario(
            userRole: UserRole::Coach,
        );

        $adminScenario = $this->buildScenario(
            userRole: UserRole::Admin,
        );

        $this->assertFalse(app(QaThreadPolicy::class)->create($coachScenario['user']));
        $this->assertFalse(app(QaThreadPolicy::class)->create($adminScenario['user']));
    }

    public function test_update_allowed_for_owner_student(): void
    {
        $scenario = $this->buildScenario();

        $this->assertTrue(app(QaThreadPolicy::class)->update($scenario['user'], $scenario['thread']));
    }

    public function test_update_denied_for_other_student(): void
    {
        $threadOwner = User::factory()->student()->create();

        $scenario = $this->buildScenario(
            threadOwner: $threadOwner
        );

        $this->assertFalse(app(QaThreadPolicy::class)->update($scenario['user'], $scenario['thread']));
    }

    public function test_delete_allowed_for_owner_student(): void
    {
        $scenario = $this->buildScenario();

        $this->assertTrue(app(QaThreadPolicy::class)->delete($scenario['user'], $scenario['thread']));
    }

    public function test_delete_allowed_for_admin(): void
    {
        $scenario = $this->buildScenario(
            userRole: UserRole::Admin,
        );

        $this->assertTrue(app(QaThreadPolicy::class)->delete($scenario['user'], $scenario['thread']));
    }

    public function test_delete_denied_for_coach(): void
    {
        $scenario = $this->buildScenario(
            userRole: UserRole::Coach,
        );

        $this->assertFalse(app(QaThreadPolicy::class)->delete($scenario['user'], $scenario['thread']));
    }

    public function test_student_can_resolve_and_unresolve_own_thread(): void
    {
        $scenario = $this->buildScenario();

        $this->assertTrue(app(QaThreadPolicy::class)->resolve($scenario['user'], $scenario['thread']));
        $this->assertTrue(app(QaThreadPolicy::class)->unresolve($scenario['user'], $scenario['thread']));
    }

    public function test_student_cannot_resolve_and_unresolve_other_users_thread(): void
    {
        $threadOwner = User::factory()->student()->create();

        $scenario = $this->buildScenario(
            threadOwner: $threadOwner
        );

        $this->assertFalse(app(QaThreadPolicy::class)->resolve($scenario['user'], $scenario['thread']));
        $this->assertFalse(app(QaThreadPolicy::class)->unresolve($scenario['user'], $scenario['thread']));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function viewAnyMatrix(): array
    {
        return [
            'admin は一覧画面に到達できる' => ['admin', true],
            'coach は一覧画面に到達できる' => ['coach', true],
            'student は一覧画面に到達できる' => ['student', true],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
     */
    public static function viewMatrix(): array
    {
        return [
            'admin は draft も view 可' => ['admin', 'draft', true, true],
            'admin は published も view 可' => ['admin', 'published', true, true],
            'admin は archived も view 可' => ['admin', 'archived', true, true],
            'coach は担当資格 (published) を view 可' => ['coach', 'published', true, true],
            'coach は非担当資格を view 不可' => ['coach', 'published', false, false],
            'student は published を view 可' => ['student', 'published', true, true],
            'student は draft を view 不可' => ['student', 'draft', true, false],
            'student は archived を view 不可' => ['student', 'archived', true, false],
        ];
    }

    /**
     * @return array{user: User, thread: QaThread}
     */
    private function buildScenario(
        UserRole $userRole = UserRole::Student,
        ?User $threadOwner = null,
    ): array {
        $user = User::factory()->create([
            'role' => $userRole,
        ]);

        $threadOwner ??= $user;

        $thread = QaThread::factory()->for($threadOwner)->create();

        return [
            'user' => $user,
            'thread' => $thread,
        ];
    }
}

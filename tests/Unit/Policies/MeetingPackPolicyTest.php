<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\MeetingPack;
use App\Models\User;
use App\Policies\MeetingPackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MeetingPackPolicy の ability × Role マトリクス検証。
 * 面談パック管理 ability 8 種 (viewAny / view / create / update / delete / publish / archive / unarchive) × 3 ロール = 24 ケースを検証する。
 */
class MeetingPackPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 面談パック管理 ability × Role のマトリクス検証。
     * Admin のみ全 ability で true、Coach / Student は全 ability で false が期待値。
     */
    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_admin_only_abilities_match_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $plan = MeetingPack::factory()->create();
        $policy = new MeetingPackPolicy;

        // Act
        $result = in_array($policyMethod, ['viewAny', 'create'], true)
            ? $policy->{$policyMethod}($actor)
            : $policy->{$policyMethod}($actor, $plan);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で ".($expected ? 'true' : 'false').' を返すべきだが反対の結果が返った',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'publish', 'archive',  'unarchive'];
        $roles = [
            'admin' => true,
            'coach' => false,
            'student' => false,
        ];

        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $caseKey = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$caseKey] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** * QaReplyPolicy の ability × Role × 本人性・資格担当状況のマトリクス検証。
 * create (student は公開済み資格、coach は担当資格の公開済みのみ)、
 * update (student / coach の自身の回答のみ)、
 * delete (admin は全回答、student / coach は自身の回答のみ) を網羅する。
 */
class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allowed_for_student_on_published_thread(): void
    {
        $scenario = $this->buildScenario();

        $this->assertTrue(app(QaReplyPolicy::class)->create($scenario['user'], $scenario['thread']));
    }

    public function test_create_allowed_for_coach_only_for_assigned_published_thread(): void
    {
        $assignedCoachScenario = $this->buildScenario(
            userRole: UserRole::Coach,
            assigned: true,
        );

        $unassignedCoachScenario = $this->buildScenario(
            userRole: UserRole::Coach,
        );

        $adminScenario = $this->buildScenario(
            userRole: UserRole::Admin,
        );

        $this->assertTrue(app(QaReplyPolicy::class)->create($assignedCoachScenario['user'], $assignedCoachScenario['thread']));
        $this->assertFalse(app(QaReplyPolicy::class)->create($unassignedCoachScenario['user'], $unassignedCoachScenario['thread']));
        $this->assertFalse(app(QaReplyPolicy::class)->create($adminScenario['user'], $adminScenario['thread']));
    }

    public function test_update_and_delete_allowed_for_owner_on_published_thread(): void
    {
        $scenario = $this->buildScenario();

        $otherStudent = User::factory()->student()->create();
        $otherCoach = User::factory()->coach()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $scenario['thread']->certification_id,
            'user_id' => $otherCoach->id,
        ]);

        $this->assertTrue(app(QaReplyPolicy::class)->update($scenario['user'], $scenario['reply']));
        $this->assertTrue(app(QaReplyPolicy::class)->delete($scenario['user'], $scenario['reply']));

        $this->assertFalse(app(QaReplyPolicy::class)->update($otherStudent, $scenario['reply']));
        $this->assertFalse(app(QaReplyPolicy::class)->delete($otherStudent, $scenario['reply']));

        $this->assertFalse(app(QaReplyPolicy::class)->update($otherCoach, $scenario['reply']));
        $this->assertFalse(app(QaReplyPolicy::class)->delete($otherCoach, $scenario['reply']));
    }

    public function test_admin_can_delete_but_cannot_update_reply(): void
    {
        $replyOwner = User::factory()->student()->create();

        $scenario = $this->buildScenario(
            replyOwner: $replyOwner,
            userRole: UserRole::Admin,
        );

        $this->assertTrue(app(QaReplyPolicy::class)->delete($scenario['user'], $scenario['reply']));
        $this->assertFalse(app(QaReplyPolicy::class)->update($scenario['user'], $scenario['reply']));
    }

    /**
     * @return array{user: User, thread:QaThread, reply QaReply}
     */
    private function buildScenario(
        UserRole $userRole = UserRole::Student,
        ?User $replyOwner = null,
        bool $assigned = false,
    ): array {
        $user = User::factory()->create([
            'role' => $userRole,
        ]);

        $replyOwner ??= $user;

        $cert = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);

        $thread = QaThread::factory()->for($cert)->create();

        $reply = QaReply::factory()->forThread($thread)->forUser($replyOwner)->create();

        if ($assigned) {
            CertificationCoachAssignment::factory()->create([
                'certification_id' => $cert->id,
                'user_id' => $user->id,
            ]);
        }

        return [
            'user' => $user,
            'thread' => $thread,
            'reply' => $reply,
        ];
    }
}

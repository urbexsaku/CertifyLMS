<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AiChatConversationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AiChatConversationPolicy;
    }

    public function test_admin_is_denied_all_conversation_operations(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $conversation = AiChatConversation::factory()->create();

        // Assert
        $this->assertFalse($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->view($admin, $conversation));
        $this->assertFalse($this->policy->create($admin));
        $this->assertFalse($this->policy->update($admin, $conversation));
        $this->assertFalse($this->policy->delete($admin, $conversation));
    }

    public function test_coach_is_denied_all_conversation_operations(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $conversation = AiChatConversation::factory()->create();

        // Assert
        $this->assertFalse($this->policy->viewAny($coach));
        $this->assertFalse($this->policy->view($coach, $conversation));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->update($coach, $conversation));
        $this->assertFalse($this->policy->delete($coach, $conversation));
    }

    public function test_student_can_manage_own_conversation(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student, 'user')->create();

        // Assert
        $this->assertTrue($this->policy->viewAny($student));
        $this->assertTrue($this->policy->view($student, $conversation));
        $this->assertTrue($this->policy->create($student));
        $this->assertTrue($this->policy->update($student, $conversation));
        $this->assertTrue($this->policy->delete($student, $conversation));
    }

    public function test_student_cannot_manage_another_students_conversation(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($otherStudent, 'user')->create();

        // Assert
        $this->assertFalse($this->policy->view($student, $conversation));
        $this->assertFalse($this->policy->update($student, $conversation));
        $this->assertFalse($this->policy->delete($student, $conversation));
    }
}

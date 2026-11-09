<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_view_conversation(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        // Assert
        $response->assertOk()
            ->assertViewIs('ai-chat.show')
            ->assertViewHas('conversation', function ($viewConversation) use ($conversation): bool {
                return $viewConversation->is($conversation);
            });
    }

    public function test_other_student_cannot_view_conversation(): void
    {
        // Arrange
        $owner = $this->createActiveStudent();
        $otherStudent = $this->createActiveStudent();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
            'enrollment_id' => $owner->default_enrollment_id,
        ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->get(route('ai-chat.conversations.show', $conversation));

        // Assert
        $response->assertForbidden();
    }

    private function createActiveStudent(): User
    {
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->learning()
            ->create([
                'user_id' => $student->id,
            ]);

        $student->forceFill([
            'default_enrollment_id' => $enrollment->id,
        ])->save();

        return $student;
    }
}

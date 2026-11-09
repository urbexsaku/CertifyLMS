<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Message;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-3.8-flash',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.timeout' => 30,
            'ai-chat.gemini.temperature' => 0.7,
            'ai-chat.title.auto_generation_enabled' => false,
            'ai-chat.conversation.daily_limit' => 50,
        ]);
    }

    public function test_owner_can_send_message_and_receive_ai_response(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        Http::fake([
            '*' => Http::response($this->successResponse('AIからの回答です。'), 200),
        ]);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            );

        // Assert
        $response->assertCreated()
            ->assertJsonPath('user_message.content', '質問です。')
            ->assertJsonPath('user_message.status', AiChatMessageStatus::Completed->value)
            ->assertJsonPath('assistant_message.content', 'AIからの回答です。')
            ->assertJsonPath('assistant_message.status', AiChatMessageStatus::Completed->value);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '質問です。',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'AIからの回答です。',
            'status' => 'completed',
        ]);
    }

    public function test_other_student_cannot_send_message_to_conversation(): void
    {
        // Arrange
        $owner = $this->createActiveStudent();
        $otherStudent = $this->createActiveStudent();
        $conversation = $this->createConversation($owner);

        Http::fake();

        // Act
        $response = $this->actingAs($otherStudent)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            );

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('ai_chat_messages', 0);
        Http::assertNothingSent();
    }

    public function test_empty_content_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => ''],
            );

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_whitespace_only_content_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '　　　'],
            );

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_content_over_two_thousand_characters_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => str_repeat('あ', 2001)],
            );

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_api_error_returns_502_json_response(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        Http::fake([
            '*' => Http::response(['error' => 'API error'], 500),
        ]);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            );

        // Assert
        $response->assertStatus(502)
            ->assertJsonPath('error_type', 'api');

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
        ]);
    }

    public function test_missing_api_key_returns_503_json_response(): void
    {
        // Arrange
        config(['ai-chat.gemini.api_key' => '']);

        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        Http::fake();

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            );

        // Assert
        $response->assertStatus(503)
            ->assertJsonPath('error_type', 'configuration');

        Http::assertNothingSent();

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
        ]);
    }

    public function test_daily_limit_returns_429_json_response(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        for ($i = 0; $i < 50; $i++) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => "質問{$i}",
                'status' => 'completed',
            ]);
        }

        Http::fake();

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '上限後の質問です。'],
            );

        // Assert
        $response->assertStatus(429);

        Http::assertNothingSent();

        $this->assertSame(
            50,
            $conversation->messages()->where('role', 'user')->count(),
        );
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

    private function createConversation(User $student): AiChatConversation
    {
        return AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(string $content): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $content],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Message;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-3.5-flash-lite',
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
        $response->assertOk()
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

    public function test_missing_api_key_returns_500_json_response(): void
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
        $response->assertStatus(500)
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

        Http::fake([
            '*' => Http::response(
                $this->successResponse('AIからの回答です。'),
                200,
            ),
        ]);

        // Act
        for ($i = 0; $i < 50; $i++) {
            $this->actingAs($student)
                ->postJson(
                    route('ai-chat.conversations.messages.store', $conversation),
                    ['content' => "質問{$i}"],
                )
                ->assertOk();
        }

        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '上限後の質問です。'],
            );

        // Assert
        $response->assertStatus(429)
            ->assertJsonPath(
                'message',
                "本日の利用上限に達しました。\n明日 0:00 以降に再度ご利用ください。",
            );

        Http::assertSentCount(50);

        $this->assertSame(
            50,
            $conversation->messages()->where('role', 'user')->count(),
        );
    }

    public function test_message_store_returns_not_found_when_feature_is_disabled(): void
    {
        // Arrange
        config(['ai-chat.enabled' => false]);
        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            );

        // Assert
        $response->assertNotFound();
    }

    public function test_daily_limit_is_independent_for_each_user(): void
    {
        // Arrange
        config(['ai-chat.conversation.daily_limit' => 2]);

        $student = $this->createActiveStudent();
        $otherStudent = $this->createActiveStudent();

        $conversation = $this->createConversation($student);
        $otherConversation = $this->createConversation($otherStudent);

        Http::fake([
            '*' => Http::response(
                $this->successResponse('AIからの回答です。'),
                200,
            ),
        ]);

        // Act
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($student)
                ->postJson(
                    route('ai-chat.conversations.messages.store', $conversation),
                    ['content' => "質問{$i}"],
                )
                ->assertOk();
        }

        $response = $this->actingAs($otherStudent)
            ->postJson(
                route('ai-chat.conversations.messages.store', $otherConversation),
                ['content' => '別ユーザーの質問です。'],
            );

        // Assert
        $response->assertOk()
            ->assertJsonPath('user_message.content', '別ユーザーの質問です。');

        Http::assertSentCount(3);
    }

    public function test_failed_api_requests_are_counted_toward_daily_limit(): void
    {
        // Arrange
        config(['ai-chat.conversation.daily_limit' => 2]);

        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        Http::fakeSequence()
            ->push($this->successResponse('正常な回答です。'), 200)
            ->push(['error' => 'API error'], 500)
            ->push(['error' => 'API error'], 500)
            ->push(['error' => 'API error'], 500);

        // Act
        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '正常な質問です。'],
            )
            ->assertOk();

        $failedResponse = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '失敗する質問です。'],
            );

        $failedResponse->assertStatus(502);

        $limitedResponse = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '上限後の質問です。'],
            );

        // Assert
        $limitedResponse->assertStatus(429);

        $this->assertSame(
            2,
            $conversation->messages()->where('role', 'user')->count(),
        );

        // 正常なAPIリクエスト1回 + 失敗したAPIリクエストのリトライ3回
        Http::assertSentCount(4);
    }

    public function test_daily_limit_is_shared_across_conversations(): void
    {
        // Arrange
        config(['ai-chat.conversation.daily_limit' => 2]);

        $student = $this->createActiveStudent();

        $firstConversation = $this->createConversation($student);
        $secondConversation = $this->createConversation($student);

        Http::fake([
            '*' => Http::response(
                $this->successResponse('AIからの回答です。'),
                200,
            ),
        ]);

        // Act
        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $firstConversation),
                ['content' => '1回目の質問です。'],
            )
            ->assertOk();

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $secondConversation),
                ['content' => '2回目の質問です。'],
            )
            ->assertOk();

        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $secondConversation),
                ['content' => '3回目の質問です。'],
            );

        // Assert
        $response->assertStatus(429);

        Http::assertSentCount(2);
    }

    public function test_daily_limit_is_reset_at_japan_midnight(): void
    {
        // Arrange
        config(['ai-chat.conversation.daily_limit' => 2]);

        $this->travelTo(
            Carbon::parse('2026-11-17 23:55:00', 'Asia/Tokyo'),
        );

        $student = $this->createActiveStudent();
        $conversation = $this->createConversation($student);

        Http::fake([
            '*' => Http::response(
                $this->successResponse('AIからの回答です。'),
                200,
            ),
        ]);

        // Act
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($student)
                ->postJson(
                    route('ai-chat.conversations.messages.store', $conversation),
                    ['content' => "日付変更前の質問{$i}"],
                )
                ->assertOk();
        }

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '上限到達後の質問です。'],
            )
            ->assertStatus(429);

        $this->travelTo(
            Carbon::parse('2026-11-18 00:01:00', 'Asia/Tokyo'),
        );

        $response = $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '日付変更後の質問です。'],
            );

        // Assert
        $response->assertOk()
            ->assertJsonPath('user_message.content', '日付変更後の質問です。');

        Http::assertSentCount(3);
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

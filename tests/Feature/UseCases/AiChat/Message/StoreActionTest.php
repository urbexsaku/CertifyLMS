<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat\Message;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use App\UseCases\AiChat\Message\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-3.8-flash',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.timeout' => 30,
            'ai-chat.gemini.temperature' => 0.7,
            'ai-chat.conversation.history_limit' => 20,
            'ai-chat.conversation.daily_limit' => 50,
            'ai-chat.title.auto_generation_enabled' => false,
        ]);
    }

    public function test_it_saves_user_message_and_completed_assistant_message_with_usage_metadata(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。', 120, 80), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame('質問です。', $result['user_message']->content);
        $this->assertSame(AiChatMessageRole::User, $result['user_message']->role);
        $this->assertSame(AiChatMessageStatus::Completed, $result['user_message']->status);

        $this->assertSame('回答です。', $result['assistant_message']->content);
        $this->assertSame(AiChatMessageRole::Assistant, $result['assistant_message']->role);
        $this->assertSame(AiChatMessageStatus::Completed, $result['assistant_message']->status);
        $this->assertSame('gemini-3.8-flash', $result['assistant_message']->model_name);
        $this->assertSame(120, $result['assistant_message']->input_tokens);
        $this->assertSame(80, $result['assistant_message']->output_tokens);
        $this->assertNotNull($result['assistant_message']->response_time_ms);

        $this->assertDatabaseCount('ai_chat_messages', 2);
        $this->assertNotNull($result['conversation']->last_message_at);
    }

    public function test_it_keeps_user_message_and_error_assistant_message_when_api_fails(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fake([
            '*' => Http::response(['error' => 'API error'], 500),
        ]);

        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException $exception) {
            // Assert
            $this->assertSame(502, $exception->getResponse()->getStatusCode());
        }

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '質問です。',
            'status' => 'completed',
        ]);

        $assistantMessage = $conversation->messages()
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(AiChatMessageStatus::Error, $assistantMessage->status);
        $this->assertSame(
            'Gemini APIから正常な応答を取得できませんでした。',
            $assistantMessage->error_message,
        );
        $this->assertNotSame('', $assistantMessage->content);
    }

    public function test_it_returns_configuration_error_when_api_key_is_missing(): void
    {
        // Arrange
        config(['ai-chat.gemini.api_key' => '']);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fake();
        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException $exception) {
            // Assert
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
        }

        Http::assertNothingSent();

        $assistantMessage = $conversation->messages()
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(AiChatMessageStatus::Error, $assistantMessage->status);
        $this->assertSame(
            'Gemini APIの設定が不足しています。',
            $assistantMessage->error_message,
        );
    }

    public function test_it_can_retry_after_api_failure_with_new_messages(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push(['error' => 'API error'], 500)
            ->push($this->successResponse('再試行後の回答です。'), 200)
            ->push($this->successResponse('再試行後のタイトルです。'), 200);

        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '最初の質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException) {
            // API失敗後に再試行する。
        }

        $result = app(StoreAction::class)($conversation, 'もう一度質問します。');

        // Assert
        $this->assertSame('もう一度質問します。', $result['user_message']->content);
        $this->assertSame('再試行後の回答です。', $result['assistant_message']->content);

        $this->assertSame(4, $conversation->messages()->count());
        $this->assertSame(
            2,
            $conversation->messages()->where('role', 'user')->count(),
        );
        $this->assertSame(
            2,
            $conversation->messages()->where('role', 'assistant')->count(),
        );
    }

    public function test_it_sends_latest_twenty_completed_messages_in_chronological_order(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        for ($i = 1; $i <= 20; $i++) {
            $conversation->messages()->create([
                'role' => $i % 2 === 0 ? 'assistant' : 'user',
                'content' => "過去のメッセージ{$i}",
                'status' => 'completed',
                'created_at' => now()->subMinutes(30 - $i),
                'updated_at' => now()->subMinutes(30 - $i),
            ]);
        }

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        app(StoreAction::class)($conversation, '今回の質問です。');

        // Assert
        Http::assertSent(function ($request): bool {
            $contents = $request->data()['contents'];

            return count($contents) === 20
                && $contents[0]['parts'][0]['text'] === '過去のメッセージ2'
                && $contents[18]['parts'][0]['text'] === '過去のメッセージ20'
                && $contents[19]['parts'][0]['text'] === '今回の質問です。';
        });
    }

    public function test_it_excludes_error_messages_from_gemini_history(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => '正常な質問です。',
            'status' => 'completed',
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => '過去のエラーです。',
            'status' => 'error',
        ]);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        app(StoreAction::class)($conversation, '今回の質問です。');

        // Assert
        Http::assertSent(function ($request): bool {
            $contents = $request->data()['contents'];
            $texts = array_map(
                fn (array $content): string => $content['parts'][0]['text'],
                $contents,
            );

            return in_array('正常な質問です。', $texts, true)
                && in_array('今回の質問です。', $texts, true)
                && ! in_array('過去のエラーです。', $texts, true);
        });
    }

    public function test_it_rejects_message_when_daily_limit_has_been_reached(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        for ($i = 0; $i < 50; $i++) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => "質問{$i}",
                'status' => 'completed',
            ]);
        }

        Http::fake();
        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '上限後の質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException $exception) {
            // Assert
            $this->assertSame(429, $exception->getResponse()->getStatusCode());
        }

        Http::assertNothingSent();

        $this->assertSame(50, $conversation->messages()->where('role', 'user')->count());
    }

    public function test_it_allows_fiftieth_message_when_daily_count_is_forty_nine(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        for ($i = 0; $i < 49; $i++) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => "質問{$i}",
                'status' => 'completed',
            ]);
        }

        Http::fakeSequence()
            ->push($this->successResponse('50回目の回答です。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '50回目の質問です。');

        // Assert
        $this->assertSame('50回目の回答です。', $result['assistant_message']->content);
        $this->assertSame(50, $conversation->messages()->where('role', 'user')->count());
        Http::assertSentCount(1);
    }

    public function test_failed_api_requests_are_counted_toward_daily_limit(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        for ($i = 0; $i < 49; $i++) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => "質問{$i}",
                'status' => 'completed',
            ]);
        }

        Http::fake([
            '*' => Http::response(['error' => 'API error'], 500),
        ]);

        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '失敗する質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException $exception) {
            // Assert
            $this->assertSame(502, $exception->getResponse()->getStatusCode());
        }

        $this->assertSame(50, $conversation->messages()->where('role', 'user')->count());

        try {
            app(StoreAction::class)($conversation, '上限後の質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException $exception) {
            $this->assertSame(429, $exception->getResponse()->getStatusCode());
        }

        Http::assertSentCount(1);
    }

    public function test_daily_limit_is_reset_at_japan_midnight(): void
    {
        // Arrange
        $this->travelTo(now()->setTimezone('Asia/Tokyo')->setTime(0, 5));

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => '前日の質問です。',
            'status' => 'completed',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '当日の質問です。');

        // Assert
        $this->assertSame('回答です。', $result['assistant_message']->content);
        Http::assertSentCount(1);
    }

    public function test_it_generates_title_after_first_successful_answer(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => true]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200)
            ->push($this->successResponse('生成されたタイトルです。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame('生成されたタイトルです。', $result['conversation']->title);
        $this->assertNotNull($result['conversation']->title_generated_at);
        Http::assertSentCount(2);
    }

    public function test_it_generates_title_after_retry_when_first_response_failed(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => true]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push(['error' => 'API error'], 500)
            ->push($this->successResponse('再試行後の回答です。'), 200)
            ->push($this->successResponse('再試行後のタイトルです。'), 200);

        $this->actingAs($user);

        // Act
        try {
            app(StoreAction::class)($conversation, '最初の質問です。');
            $this->fail('HttpResponseException が発生する必要があります。');
        } catch (HttpResponseException) {
            // API失敗後に再試行する。
        }

        $result = app(StoreAction::class)($conversation, '再試行の質問です。');

        // Assert
        $this->assertSame('再試行後のタイトルです。', $result['conversation']->title);
        $this->assertNotNull($result['conversation']->title_generated_at);
        Http::assertSentCount(3);
    }

    public function test_it_does_not_regenerate_title_after_title_has_been_generated(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => true]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        $conversation->update([
            'title' => '既存タイトル',
            'title_generated_at' => now()->subDay(),
        ]);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame('既存タイトル', $result['conversation']->title);
        Http::assertSentCount(1);
    }

    public function test_it_does_not_overwrite_manually_edited_title(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => true]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        $conversation->update([
            'title' => '手動で変更したタイトル',
            'title_manually_edited_at' => now(),
        ]);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame('手動で変更したタイトル', $result['conversation']->title);
        Http::assertSentCount(1);
    }

    public function test_it_skips_title_generation_when_auto_generation_is_disabled(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => false]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame(
            config('ai-chat.title.provisional'),
            $result['conversation']->title,
        );
        $this->assertNull($result['conversation']->title_generated_at);
        Http::assertSentCount(1);
    }

    public function test_it_keeps_completed_answer_when_title_generation_fails(): void
    {
        // Arrange
        config(['ai-chat.title.auto_generation_enabled' => true]);

        $user = User::factory()->student()->create();
        $conversation = $this->createConversation($user);

        Http::fakeSequence()
            ->push($this->successResponse('回答です。'), 200)
            ->push(['error' => 'Title generation failed'], 500);

        $this->actingAs($user);

        // Act
        $result = app(StoreAction::class)($conversation, '質問です。');

        // Assert
        $this->assertSame('回答です。', $result['assistant_message']->content);
        $this->assertSame(AiChatMessageStatus::Completed, $result['assistant_message']->status);
        $this->assertSame(
            config('ai-chat.title.provisional'),
            $result['conversation']->title,
        );
        $this->assertNull($result['conversation']->title_generated_at);
        Http::assertSentCount(2);
    }

    private function createConversation(User $user): AiChatConversation
    {
        return AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'title' => config('ai-chat.title.provisional'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(
        string $content,
        int $inputTokens = 100,
        int $outputTokens = 50,
    ): array {
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
                'promptTokenCount' => $inputTokens,
                'candidatesTokenCount' => $outputTokens,
            ],
        ];
    }
}

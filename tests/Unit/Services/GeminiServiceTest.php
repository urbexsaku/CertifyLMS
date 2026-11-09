<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiApiException;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    private function setGeminiConfig(): void
    {
        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.base_url' => 'https://gemini.test/v1beta',
            'ai-chat.gemini.model' => 'test-model',
            'ai-chat.gemini.timeout' => 30,
            'ai-chat.gemini.temperature' => 0.7,
            'ai-chat.prompts.system' => '学習支援を行うアシスタントです。',
            'ai-chat.title.max_length' => 30,
        ]);
    }

    private function successfulResponse(array $overrides = []): array
    {
        return array_replace_recursive([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '回答テキスト'],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
            ],
        ], $overrides);
    }

    public function test_generates_response_with_expected_request_payload(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response($this->successfulResponse(), 200)]);

        // Act
        $result = app(GeminiService::class)->generateResponse([
            ['role' => 'user', 'content' => '最初の質問'],
            ['role' => 'assistant', 'content' => '前回の回答'],
            ['role' => 'user', 'content' => '追加の質問'],
        ]);

        // Assert
        $this->assertSame('回答テキスト', $result['content']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gemini.test/v1beta/models/test-model:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && $request['contents'] === [
                    ['role' => 'user', 'parts' => [['text' => '最初の質問']]],
                    ['role' => 'model', 'parts' => [['text' => '前回の回答']]],
                    ['role' => 'user', 'parts' => [['text' => '追加の質問']]],
                ]
                && $request['generationConfig']['temperature'] === 0.7;
        });
    }

    public function test_includes_section_context_in_system_prompt(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response($this->successfulResponse(), 200)]);

        $certification = new Certification(['name' => '資格テスト']);
        $part = new Part;
        $part->setRelation('certification', $certification);
        $chapter = new Chapter;
        $chapter->setRelation('part', $part);
        $section = new Section(['title' => '教材タイトル', 'body' => '教材本文']);
        $section->setRelation('chapter', $chapter);

        // Act
        app(GeminiService::class)->generateResponse(
            [['role' => 'user', 'content' => '質問']],
            $section,
        );

        // Assert
        Http::assertSent(fn ($request): bool => str_contains($request['systemInstruction']['parts'][0]['text'], '資格名：資格テスト')
            && str_contains($request['systemInstruction']['parts'][0]['text'], '教材タイトル：教材タイトル')
            && str_contains($request['systemInstruction']['parts'][0]['text'], '教材本文：教材本文')
        );
    }

    public function test_includes_enrollment_certification_context_without_section(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response($this->successfulResponse(), 200)]);

        $certification = new Certification([
            'name' => '資格テスト',
            'description' => '資格の説明',
        ]);
        $enrollment = new Enrollment;
        $enrollment->setRelation('certification', $certification);

        // Act
        app(GeminiService::class)->generateResponse(
            [['role' => 'user', 'content' => '質問']],
            enrollment: $enrollment,
        );

        // Assert
        Http::assertSent(fn ($request): bool => str_contains($request['systemInstruction']['parts'][0]['text'], '資格名：資格テスト')
            && str_contains($request['systemInstruction']['parts'][0]['text'], '資格説明：資格の説明')
        );
    }

    public function test_returns_response_and_usage_metadata(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response($this->successfulResponse(), 200)]);

        // Act
        $result = app(GeminiService::class)->generateResponse([
            ['role' => 'user', 'content' => '質問'],
        ]);

        // Assert
        $this->assertSame('回答テキスト', $result['content']);
        $this->assertSame('test-model', $result['model_name']);
        $this->assertSame(100, $result['input_tokens']);
        $this->assertSame(50, $result['output_tokens']);
        $this->assertIsInt($result['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $result['response_time_ms']);
    }

    public function test_returns_null_token_counts_when_usage_metadata_is_missing(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => '回答テキスト']]]],
            ],
        ], 200)]);

        // Act
        $result = app(GeminiService::class)->generateResponse([
            ['role' => 'user', 'content' => '質問'],
        ]);

        // Assert
        $this->assertSame('回答テキスト', $result['content']);
        $this->assertNull($result['input_tokens']);
        $this->assertNull($result['output_tokens']);
    }

    public function test_throws_configuration_error_when_api_key_is_missing(): void
    {
        // Arrange
        $this->setGeminiConfig();
        config(['ai-chat.gemini.api_key' => null]);

        // Act
        try {
            app(GeminiService::class)->generateResponse([
                ['role' => 'user', 'content' => '質問'],
            ]);
            $this->fail('APIキー未設定の場合は例外が発生するはず');
        } catch (GeminiApiException $exception) {
            // Assert
            $this->assertTrue($exception->isConfigurationError());
        }

        Http::assertNothingSent();
    }

    public function test_throws_exception_when_api_returns_error(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response(['error' => 'Unavailable'], 503)]);

        // Act
        try {
            app(GeminiService::class)->generateResponse([
                ['role' => 'user', 'content' => '質問'],
            ]);
            $this->fail('APIエラーの場合は例外が発生するはず');
        } catch (GeminiApiException $exception) {
            // Assert
            $this->assertSame(503, $exception->upstreamStatus());
            $this->assertFalse($exception->isConfigurationError());
        }
    }

    public function test_throws_exception_when_response_is_invalid(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fakeSequence()
            ->push('invalid-json', 200)
            ->push(['candidates' => [['content' => ['parts' => []]]]], 200);

        // Act
        try {
            app(GeminiService::class)->generateResponse([['role' => 'user', 'content' => '質問']]);
            $this->fail('不正なレスポンス形式の場合は例外が発生するはず');
        } catch (GeminiApiException $exception) {
            // Assert
            $this->assertSame('Gemini APIのレスポンス形式が不正です。', $exception->getMessage());
            $this->assertSame(200, $exception->upstreamStatus());
        }

        // Act
        try {
            app(GeminiService::class)->generateResponse([['role' => 'user', 'content' => '質問']]);
            $this->fail('回答本文が空の場合は例外が発生するはず');
        } catch (GeminiApiException $exception) {
            // Assert
            $this->assertSame('Gemini APIの回答本文が空です。', $exception->getMessage());
            $this->assertSame(200, $exception->upstreamStatus());
        }
    }

    public function test_generates_title_from_question_and_answer(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => '学習内容の確認']]]],
            ],
        ], 200)]);

        // Act
        $title = app(GeminiService::class)->generateTitle('質問内容', '回答内容');

        // Assert
        $this->assertSame('学習内容の確認', $title);
        Http::assertSent(fn ($request): bool => str_contains($request['contents'][0]['parts'][0]['text'], '質問内容')
            && str_contains($request['contents'][0]['parts'][0]['text'], '回答内容')
        );
    }

    public function test_normalizes_and_truncates_generated_title(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => "「 これは\n長いタイトルです。追加の説明も含みます 」"]]]],
            ],
        ], 200)]);

        // Act
        $title = app(GeminiService::class)->generateTitle('質問', '回答');

        // Assert
        $this->assertSame('これは 長いタイトルです。追加の説明も含みます', $title);
        $this->assertLessThanOrEqual(30, mb_strlen($title));
        $this->assertStringNotContainsString('「', $title);
        $this->assertStringNotContainsString('」', $title);
        $this->assertStringNotContainsString("\n", $title);
    }

    public function test_throws_exception_when_title_generation_fails(): void
    {
        // Arrange
        $this->setGeminiConfig();
        Http::fake(['gemini.test/*' => Http::response(['error' => 'Unavailable'], 503)]);

        // Act
        try {
            app(GeminiService::class)->generateTitle('質問', '回答');
            $this->fail('タイトル生成API失敗時は例外が発生するはず');
        } catch (GeminiApiException $exception) {
            // Assert
            $this->assertSame(503, $exception->upstreamStatus());
        }
    }
}

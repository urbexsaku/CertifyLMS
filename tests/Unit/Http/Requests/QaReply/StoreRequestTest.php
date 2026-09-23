<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板の回答新規作成 StoreRequest のバリデーション検証。
 * 必須 body を valid + invalid で網羅し、
 * authorize は Admin・未担当Coach を拒否することを検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => '質問回答テストの本文です。',
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('qa_replies', ['body' => '質問回答テストの本文です。']);
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $payload = array_merge([
            'body' => '質問回答テストの本文です。',
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.replies.store', $thread), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    #[DataProvider('whitespaceOnlyPayloads')]
    public function test_validation_fails_for_whitespace_only(string $invalidField, string $invalidValue): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $payload = array_merge([
            'body' => '質問回答テストの本文です。',
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.replies.store', $thread), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    public function test_authorize_returns_false_for_unassigned_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', ['thread' => $thread]), [
            'body' => '質問回答テストの本文です。',
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidFieldPayloads(): array
    {
        return [
            'body 未指定で 422' => ['body', ''],
            'body 5001 文字で 422' => ['body', str_repeat('b', 5001)],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function whitespaceOnlyPayloads(): array
    {
        return [
            'body 半角空白のみで 422' => ['body', '   '],
            'body 全角空白のみで 422' => ['body', '　　'],
        ];
    }
}

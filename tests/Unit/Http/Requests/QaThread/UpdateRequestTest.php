<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板スレッド更新 UpdateRequest の rules() バリデーション検証。
 * title / body の文字数を Validator::make で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();

        // Act
        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => '更新後の質問タイトル',
            'body' => '更新後の質問本文です。',
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id, 'title' => '更新後の質問タイトル']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();
        $payload = array_merge([
            'title' => '更新後の質問タイトル',
            'body' => '更新後の質問本文です。',
        ], $overrides);

        // Act
        $response = $this->actingAs($student)->patchJson(route('qa-board.update', $thread), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    #[DataProvider('whitespaceOnlyPayloads')]
    public function test_validation_fails_for_whitespace_only(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();
        $payload = array_merge([
            'title' => '更新後の質問タイトル',
            'body' => '更新後の質問本文です。',
        ], $overrides);

        // Act
        $response = $this->actingAs($student)->patchJson(route('qa-board.update', $thread), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_owner(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $nonOwner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();

        // Act
        $response = $this->actingAs($nonOwner)->patch(route('qa-board.update', $thread), [
            'title' => '更新後の質問タイトル',
            'body' => '更新後の質問本文です。',
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 201 文字で 422' => [['title' => str_repeat('a', 201)], 'title'],
            'body 未指定で 422' => [['body' => ''], 'body'],
            'body 5001 文字で 422' => [['body' => str_repeat('b', 5001)], 'body'],
        ];
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function whitespaceOnlyPayloads(): array
    {
        return [
            'title 半角空白のみで 422' => [['title' => '   '], 'title'],
            'title 全角空白のみで 422' => [['title' => '　　'], 'title'],
            'body 半角空白のみで 422' => [['body' => '   '], 'body'],
            'body 全角空白のみで 422' => [['body' => '　　'], 'body'],
        ];
    }
}

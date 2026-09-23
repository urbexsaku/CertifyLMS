<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板スレッド新規作成 StoreRequest のバリデーション検証。
 * 必須 certification_id (exists) / title / body の形式・文字数・空白のみ入力を検証し、
 * authorize は Student かつ資格が公開状態の場合のみ許可されることを検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('qa_threads', ['title' => '質問タイトル']);
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $payload = array_merge([
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    #[DataProvider('whitespaceOnlyPayloads')]
    public function test_validation_fails_for_whitespace_only(string $invalidField, string $invalidValue): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $payload = array_merge([
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    public function test_validation_fails_for_nonexistent_certification_id(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => (string) Str::ulid(),
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('certification_id');
    }

    #[DataProvider('nonPublishedCertificationStatuses')]
    public function test_authorize_returns_false_for_non_published_certification(CertificationStatus $status): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->create([
            'status' => $status->value,
        ]);

        // Act
        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
        ]);

        // Assert
        $response->assertForbidden();
    }

    #[DataProvider('nonStudentRoles')]
    public function test_authorize_returns_false_for_non_student(string $role): void
    {
        // Arrange
        $user = $role === 'coach'
            ? User::factory()->coach()->create()
            : User::factory()->admin()->create();

        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($user)->postJson(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問テストの本文です。',
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
            'certification_id 未指定で 422' => ['certification_id', ''],
            'title 未指定で 422' => ['title', ''],
            'title 201 文字で 422' => ['title', str_repeat('a', 201)],
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
            'title 半角空白のみで 422' => ['title', '   '],
            'title 全角空白のみで 422' => ['title', '　　'],
            'body 半角空白のみで 422' => ['body', '   '],
            'body 全角空白のみで 422' => ['body', '　　'],
        ];
    }

    /**
     * @return array<string, array{0: CertificationStatus}>
     */
    public static function nonPublishedCertificationStatuses(): array
    {
        return [
            'Draft' => [CertificationStatus::Draft],
            'Archived' => [CertificationStatus::Archived],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonStudentRoles(): array
    {
        return [
            'Coach' => ['coach'],
            'Admin' => ['admin'],
        ];
    }
}

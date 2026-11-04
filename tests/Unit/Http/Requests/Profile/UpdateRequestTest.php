<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\UpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プロフィール更新 UpdateRequest の rules() バリデーション検証。
 * name / bio / meeting_url(コーチのみ) の数値レンジ・文字数を Validator::make で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);
        $payload = ['name' => 'テストユーザー', 'bio' => '自己紹介文', 'meeting_url' => 'https://meet.google.com/updated-coach-room'];

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);
        $payload = array_merge(['name' => 'テストユーザー', 'bio' => '自己紹介文', 'meeting_url' => 'https://meet.google.com/updated-coach-room'], [$field => $value]);

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    public function test_fails_when_non_coach_submits_meeting_url(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $student);

        $payload = ['name' => 'テストユーザー', 'bio' => '自己紹介文', 'meeting_url' => 'https://meet.google.com/updated-room'];

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'meeting_url',
            $validator->errors()->toArray()
        );
    }

    public function test_authorize_returns_true_for_authenticated_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    public function test_authorize_returns_false_for_guest(): void
    {
        // Arrange
        $request = new UpdateRequest;
        $request->setUserResolver(fn () => null);

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'name 未指定で エラー' => ['name', ''],
            'name 51 文字で エラー' => ['name', str_repeat('a', 51)],
            'bio 1001 文字で エラー' => ['bio', str_repeat('b', 1001)],
            'meeting_url 不正なURL' => ['meeting_url', 'unknown'],
            'meeting_url 501文字' => [
                'meeting_url',
                'https://example.com/'.str_repeat('a', 481),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Http\Requests\Plan\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プランマスタ更新 UpdateRequest の rules() バリデーション検証。
 * name・description・duration_days・default_meeting_quota・sort_order の数値レンジ・文字数を Validator::make で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $payload = ['name' => 'テストプラン', 'description' => 'テスト用のプラン', 'duration_days' => 100, 'default_meeting_quota' => 10, 'sort_order' => 5];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCases')]
    public function test_validation_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $payload = array_merge(['name' => 'テストプラン', 'description' => 'テスト用のプラン', 'duration_days' => 100, 'default_meeting_quota' => 10, 'sort_order' => 5], [$field => $value]);

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'name 未指定で 422' => ['name', ''],
            'name 101 文字で 422' => ['name', str_repeat('a', 101)],
            'description 2001 文字で 422' => ['description', str_repeat('b', 2001)],
            'duration_days 負数で 422' => ['duration_days', -1],
            'duration_days 3651 で 422' => ['duration_days', 3651],
            'duration_days 非整数で 422' => ['duration_days', 'abc'],
            'default_meeting_quota 負数で 422' => ['default_meeting_quota', -1],
            'default_meeting_quota 1001 で 422' => ['default_meeting_quota', 1001],
            'default_meeting_quota 非整数で 422' => ['default_meeting_quota', 'abc'],
            'sort_order 負数で 422' => ['sort_order', -1],
            'sort_order 非整数で 422' => ['sort_order', 'abc'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Http\Requests\MeetingPack\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パックマスタ更新 UpdateRequest の rules() バリデーション検証。
 * name・description・meeting_count・price・stripe_price_id・sort_order の数値レンジ・文字数を Validator::make で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $payload = ['name' => 'テスト面談パック', 'description' => 'テスト用の面談パック', 'meeting_count' => 1, 'price' => 1000, 'sort_order' => 5];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCases')]
    public function test_validation_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $payload = array_merge(['name' => 'テスト面談パック', 'description' => 'テスト用の面談パック', 'meeting_count' => 1, 'price' => 1000, 'sort_order' => 5], [$field => $value]);

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
            'meeting_count 負数で 422' => ['meeting_count', -1],
            'meeting_count 101 で 422' => ['meeting_count', 101],
            'meeting_count 非整数で 422' => ['meeting_count', 'abc'],
            'price 負数で 422' => ['price', -1],
            'price 1000001 で 422' => ['price', 1000001],
            'price 非整数で 422' => ['price', 'abc'],
            'stripe_price_id 256 文字で 422' => ['stripe_price_id', str_repeat('b', 256)],
            'sort_order 負数で 422' => ['sort_order', -1],
            'sort_order 非整数で 422' => ['sort_order', 'abc'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パックマスタ新規作成 FormRequest (`app/Http/Requests/MeetingPack/StoreRequest.php`) のバリデーション検証。
 * 必須 / 型 / 文字数 / 数値レンジ の検証を網羅する。
 * authorize は admin のみ通過することを併せて検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'テスト面談パック',
            'description' => 'テスト用の面談パック',
            'meeting_count' => 1,
            'price' => 1000,
            'sort_order' => 5,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('meeting_packs', [
            'name' => 'テスト面談パック',
            'description' => 'テスト用の面談パック',
        ]);
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $payload = array_merge([
            'name' => 'テスト面談パック',
            'description' => 'テスト用の面談パック',
            'meeting_count' => 1,
            'price' => 1000,
            'sort_order' => 5,
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange: coach は MeetingPackPolicy::create で false が返るはず
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.meeting-packs.store'), [
            'name' => 'テスト面談パック',
            'description' => 'テスト用の面談パック',
            'meeting_count' => 1,
            'price' => 1000,
            'sort_order' => 5,
        ]);

        // Assert: ロール Middleware で 403、または Policy で 403
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidFieldPayloads(): array
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

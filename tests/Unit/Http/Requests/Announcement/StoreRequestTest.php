<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_all_students_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('announcements', [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
        ]);
    }

    public function test_validation_passes_with_certification_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('announcements', [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
        ]);
    }

    public function test_validation_passes_with_user_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $user = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $user->id,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('announcements', [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $payload = array_merge([
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    #[DataProvider('invalidTargetIdPayloads')]
    public function test_validation_fails_for_target_id(string $targetType, array $overrides, string $expectedErrorField, ?array $userAttributes = null): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        if ($userAttributes !== null) {
            $user = User::factory()->create($userAttributes);
            $overrides['target_user_id'] = $user->id;
        }

        $payload = array_merge([
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => $targetType,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act
        $coachResponse = $this->actingAs($coach)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);
        $studentResponse = $this->actingAs($student)->post(route('admin.announcements.store'), [
            'title' => 'お知らせテスト',
            'body' => 'お知らせテスト本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $coachResponse->assertForbidden();
        $studentResponse->assertForbidden();
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
            'body 5001 文字で 422' => [['body' => str_repeat('a', 5001)], 'body'],
            'target_type 不正で 422' => [['target_type' => 'invalid'], 'target_type'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3?: array<string, string>}>
     */
    public static function invalidTargetIdPayloads(): array
    {
        return [
            'target_certification_id 未指定で422' => [
                AnnouncementTargetType::Certification->value,
                [],
                'target_certification_id',
            ],
            'target_certification_id 存在しないULIDで422' => [
                AnnouncementTargetType::Certification->value,
                ['target_certification_id' => (string) Str::ulid()],
                'target_certification_id',
            ],
            'target_user_id 未指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
            ],
            'target_user_id 存在しないULIDで422' => [
                AnnouncementTargetType::User->value,
                ['target_user_id' => (string) Str::ulid()],
                'target_user_id',
            ],
            'target_user_id コーチ指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
                ['role' => UserRole::Coach->value, 'status' => UserStatus::InProgress->value],
            ],
            'target_user_id 管理者指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
                ['role' => UserRole::Admin->value, 'status' => UserStatus::InProgress->value],
            ],
            'target_user_id 招待中の受講生指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
                ['role' => UserRole::Student->value, 'status' => UserStatus::Invited->value],
            ],
            'target_user_id 卒業済みの受講生指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
                ['role' => UserRole::Student->value, 'status' => UserStatus::Graduated->value],
            ],
            'target_user_id 退会済みの受講生指定で422' => [
                AnnouncementTargetType::User->value,
                [],
                'target_user_id',
                ['role' => UserRole::Student->value, 'status' => UserStatus::Withdrawn->value],
            ],
        ];
    }
}

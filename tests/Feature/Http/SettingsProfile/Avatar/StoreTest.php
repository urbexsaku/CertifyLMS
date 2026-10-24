<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * アバター画像アップロードの HTTP 統合テスト。
 *
 * 認証、入力バリデーション、対応する画像形式・ファイルサイズの制限、
 * 画像ファイルの保存、既存画像の置き換え、
 * 旧画像の削除失敗時の動作を検証する。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_avatar(): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('public');
        $avatar = UploadedFile::fake()->image('avatar.png');

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を更新しました。');

        $user->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $user->avatar_url,
        ]);

        $disk = Storage::disk('public');
        $path = 'avatars/'.basename(parse_url($user->avatar_url, PHP_URL_PATH));

        $this->assertSame($disk->url($path), $user->avatar_url);
        $disk->assertExists($path);
    }

    public function test_upload_replaces_existing_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $disk = Storage::disk('public');
        $oldPath = 'avatars/original.png';
        $disk->put($oldPath, 'old avatar');

        $user = User::factory()->create([
            'avatar_url' => $disk->url($oldPath),
        ]);

        $avatar = UploadedFile::fake()->image('updated.png');

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を更新しました。');

        $user->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $user->avatar_url,
        ]);

        $newPath = 'avatars/'.basename(parse_url($user->avatar_url, PHP_URL_PATH));

        $this->assertSame($disk->url($newPath), $user->avatar_url);
        $disk->assertExists($newPath);
        $disk->assertMissing($oldPath);
    }

    public function test_upload_succeeds_and_logs_warning_when_old_avatar_deletion_fails(): void
    {
        // Arrange
        Storage::fake('public');

        $disk = Storage::disk('public');
        $oldPath = 'avatars/original.png';
        $disk->put($oldPath, 'old avatar');

        $user = User::factory()->create([
            'avatar_url' => $disk->url($oldPath),
        ]);

        $diskMock = Mockery::mock($disk)->makePartial();
        $diskMock->shouldReceive('delete')
            ->once()
            ->with($oldPath)
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($diskMock);

        Log::shouldReceive('warning')
            ->once()
            ->with('旧アイコン画像の削除に失敗しました。', [
                'user_id' => $user->id,
                'path' => $oldPath,
            ]);

        $avatar = UploadedFile::fake()->image('updated.png');

        // Act
        $response = $this->actingAs($user)->post(
            route('settings.avatar.store'),
            ['avatar' => $avatar],
        );

        // Assert
        $response->assertRedirect(
            route('settings.profile.edit', ['tab' => 'profile']),
        );
        $response->assertSessionHas('success', 'アイコン画像を更新しました。');

        $user->refresh();

        $this->assertNotSame(
            $disk->url($oldPath),
            $user->avatar_url,
        );

        $newPath = 'avatars/'.basename(
            parse_url($user->avatar_url, PHP_URL_PATH),
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $user->avatar_url,
        ]);

        $disk->assertExists($newPath);
        $disk->assertExists($oldPath);
    }

    public function test_guest_cannot_upload_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $disk = Storage::disk('public');
        $existingPath = 'avatars/original.png';
        $disk->put($existingPath, 'existing avatar');

        $user = User::factory()->create([
            'avatar_url' => $disk->url($existingPath),
        ]);

        $avatar = UploadedFile::fake()->image('updated.png');

        // Act
        $response = $this->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $disk->url($existingPath),
        ]);

        $disk->assertExists($existingPath);
        $this->assertSame([$existingPath], $disk->allFiles('avatars'));
    }

    public function test_graduated_student_can_upload_avatar(): void
    {
        // Arrange
        $user = User::factory()->student()->graduated()->create();
        Storage::fake('public');
        $avatar = UploadedFile::fake()->image('avatar.png');

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を更新しました。');

        $user->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $user->avatar_url,
        ]);

        $disk = Storage::disk('public');
        $path = 'avatars/'.basename(parse_url($user->avatar_url, PHP_URL_PATH));

        $this->assertSame($disk->url($path), $user->avatar_url);
        $disk->assertExists($path);
    }

    public function test_upload_requires_avatar_file(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors(['avatar']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('public');
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        // Assert
        $response->assertSessionHasErrors(['avatar']);

        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_upload_rejects_file_exceeding_max_size(): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('public');
        $avatar = UploadedFile::fake()->image('avatar.png')->size(2049);

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertSessionHasErrors(['avatar']);

        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /**
     * @dataProvider supportedImageTypes
     */
    public function test_upload_accepts_supported_image_types(string $extension): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('public');
        $avatar = UploadedFile::fake()->image("avatar.{$extension}");

        // Act
        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $avatar,
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を更新しました。');
    }

    /**
     * アップロードを許可する画像形式のテストデータを返す。
     *
     * @return array<string, array{0: string}> 画像の拡張子を含むテストデータ
     */
    public static function supportedImageTypes(): array
    {
        return [
            'PNG' => ['png'],
            'JPG' => ['jpg'],
            'JPEG' => ['jpeg'],
            'WebP' => ['webp'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_own_avatar(): void
    {
        // Arrange
        Storage::fake('public');
        Storage::disk('public')->put('avatars/original.png', 'dummy content');

        $user = User::factory()->create([
            'avatar_url' => 'http://localhost:8000/storage/avatars/original.png',
        ]);

        // Act
        $response = $this->actingAs($user)->delete(route('settings.avatar.destroy'));

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を削除しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
        Storage::disk('public')->assertMissing('avatars/original.png');
    }

    public function test_guest_cannot_delete_avatar(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $existingPath = 'avatars/original.png';
        $disk->put($existingPath, 'existing avatar');

        $user = User::factory()->create([
            'avatar_url' => $disk->url($existingPath),
        ]);

        // Act
        $response = $this->delete(route('settings.avatar.destroy'));

        // Assert
        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => $disk->url($existingPath),
        ]);

        $disk->assertExists($existingPath);
        $this->assertSame([$existingPath], $disk->allFiles('avatars'));
    }

    public function test_graduated_student_can_delete_avatar(): void
    {
        // Arrange
        Storage::fake('public');
        Storage::disk('public')->put('avatars/original.png', 'dummy content');

        $user = User::factory()->student()->graduated()->create([
            'avatar_url' => 'http://localhost:8000/storage/avatars/original.png',
        ]);

        // Act
        $response = $this->actingAs($user)->delete(route('settings.avatar.destroy'));

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アイコン画像を削除しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
        Storage::disk('public')->assertMissing('avatars/original.png');
    }
}

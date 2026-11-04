<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Password;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_password(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHas('success', 'パスワードを更新しました。');

        $user->refresh();

        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check($currentPassword, $user->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => '',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $user->refresh();

        $this->assertFalse(Hash::check($newPassword, $user->password));
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }

    public function test_password_update_rejects_incorrect_current_password(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $user->refresh();

        $this->assertFalse(Hash::check($newPassword, $user->password));
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }

    public function test_password_update_requires_password_confirmation(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => 'incorrect-password',
        ],
        );

        // Assert
        $response->assertSessionHasErrorsIn('updatePassword', ['password']);

        $user->refresh();

        $this->assertFalse(Hash::check($newPassword, $user->password));
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }

    public function test_password_update_requires_minimum_length(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'pass';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertSessionHasErrorsIn('updatePassword', ['password']);

        $user->refresh();

        $this->assertFalse(Hash::check($newPassword, $user->password));
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }

    public function test_guest_cannot_update_password(): void
    {
        // Arrange

        // Act
        $response = $this->put(route('settings.password.update'), [
            'current_password' => 'Current-password',
            'password' => 'New-password',
            'password_confirmation' => 'New-password',
        ],
        );

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_graduated_student_can_update_password(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->student()->graduated()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHas('success', 'パスワードを更新しました。');

        $user->refresh();

        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check($currentPassword, $user->password));
    }

    public function test_validation_error_returns_to_password_tab(): void
    {
        // Arrange
        $currentPassword = 'Current-password';
        $newPassword = 'New-password';

        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act
        $response = $this->from(route('settings.profile.edit', ['tab' => 'password']))->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => '',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        );

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $response->assertSessionMissing('_old_input.current_password');
        $response->assertSessionMissing('_old_input.password');
        $response->assertSessionMissing('_old_input.password_confirmation');
    }
}

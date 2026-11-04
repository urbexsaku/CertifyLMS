<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * プロフィール更新 Update の Feature テスト。
     *
     * プロフィール情報の更新、コーチの面談URL更新、
     * コーチ以外による面談URL更新の拒否を検証する。
     */
    public function test_authenticated_user_can_update_profile(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'bio' => '自己紹介文',
        ]);

        // Act
        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'bio' => '更新後の自己紹介文',
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新後の氏名',
            'bio' => '更新後の自己紹介文',
        ]);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/coach-room',
        ]);

        // Act
        $response = $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'meeting_url' => 'https://meet.google.com/updated-coach-room',
        ]);

        // Assert

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => 'https://meet.google.com/updated-coach-room',
        ]);
    }

    public function test_coach_can_clear_meeting_url(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/coach-room',
        ]);

        // Act
        $response = $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'meeting_url' => '',
        ]);

        // Assert

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => null,
        ]);
    }

    public function test_non_coach_cannot_update_meeting_url(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        // Act
        $studentResponse = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'meeting_url' => 'https://meet.google.com/student-room',
        ]);
        $adminResponse = $this->actingAs($admin)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'meeting_url' => 'https://meet.google.com/student-room',
        ]);

        // Assert
        $studentResponse->assertSessionHasErrors(['meeting_url']);
        $adminResponse->assertSessionHasErrors(['meeting_url']);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'meeting_url' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'meeting_url' => null,
        ]);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        // Arrange
        $user = User::factory()->student()->graduated()->create([
            'name' => '修了済受講生',
            'bio' => '自己紹介文',
        ]);

        // Act
        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '更新後の氏名',
            'bio' => '更新後の自己紹介文',
        ]);

        // Assert
        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新後の氏名',
            'bio' => '更新後の自己紹介文',
        ]);
    }

    public function test_validation_error_returns_to_profile_tab_with_old_input(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '入力した氏名',
            'bio' => '入力した自己紹介文',
            'meeting_url' => 123,
        ]);

        // Assert
        $response->assertSessionHasErrors(['meeting_url']);

        $response->assertSessionHas('_old_input.name', '入力した氏名');
        $response->assertSessionHas('_old_input.bio', '入力した自己紹介文');
    }
}

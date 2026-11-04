<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プロフィール設定ページ (`settings.profile.edit`) の描画を検証する Feature テスト。
 * 認証済みユーザーのプロフィール情報、タブ切り替え、メールアドレスの読み取り専用表示、
 * コーチのみの固定面談 URL 表示、およびアバター画像と削除フォームの表示を検証する。
 */
class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        // Arrange

        // Act
        $response = $this->get(route('settings.profile.edit'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile_settings(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create([
            'name' => 'テストユーザー',
            'bio' => '自己紹介文',
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('settings.profile');
        $this->assertSame($user->id, $response->viewData('user')->id);
        $response->assertSeeText('テストユーザー');
        $response->assertSeeText('自己紹介文');
        $response->assertSeeText('受講生');
        $response->assertSeeText('受講中');
    }

    public function test_selected_tab_is_preserved_in_url_and_after_reload(): void
    {
        // Arrange
        $user = User::factory()->create();

        $passwordUrl = route('settings.profile.edit', ['tab' => 'password']);
        $profileUrl = route('settings.profile.edit', ['tab' => 'profile']);

        // Act
        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        // Assert: 各タブのリンク先を確認
        $response->assertOk();
        $response->assertSee('href="'.$profileUrl.'"', false);
        $response->assertSee('href="'.$passwordUrl.'"', false);

        // Act: パスワードタブのURLにアクセス
        $response = $this->get($passwordUrl);

        // Assert: パスワードタブが選択状態で表示される
        $response->assertOk();
        $response->assertSee('href="'.$passwordUrl.'"', false);
        $response->assertSee('aria-selected="true"', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertSeeText('パスワード変更');

        // Act: 同じURLに再アクセス（再読み込み相当）
        $response = $this->get($passwordUrl);

        // Assert: 再アクセス後も選択状態が維持される
        $response->assertOk();
        $response->assertOk();

        $html = preg_replace('/\s+/', ' ', $response->getContent());

        $this->assertStringContainsString('href="'.$passwordUrl.'" role="tab" aria-selected="true" aria-current="page"', $html);
    }

    public function test_email_field_is_readonly(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertSee('readonly', false);
        $response->assertSee('value="test@example.com"', false);
    }

    public function test_coach_can_see_meeting_url_field_and_current_value(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/coach-room',
        ]);

        // Act
        $response = $this->actingAs($coach)->get(route('settings.profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertSeeText('固定面談 URL');
        $response->assertSee('https://meet.google.com/coach-room');
    }

    public function test_non_coach_cannot_see_meeting_url_field(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertDontSeeText('固定面談 URL');
    }

    public function test_avatar_is_displayed_when_registered(): void
    {
        // Arrange
        $avatarUrl = 'http://localhost:8000/storage/avatars/test.png';

        $user = User::factory()->create([
            'avatar_url' => $avatarUrl,
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertSee('src="'.$avatarUrl.'"', false);
    }

    public function test_avatar_delete_form_is_displayed_only_when_avatar_exists(): void
    {
        // Arrange
        $userWithAvatar = User::factory()->create([
            'avatar_url' => 'http://localhost:8000/storage/avatars/test.png',
        ]);

        $userWithoutAvatar = User::factory()->create([
            'avatar_url' => null,
        ]);

        // Act
        $responseWithAvatar = $this->actingAs($userWithAvatar)->get(route('settings.profile.edit'));
        $responseWithoutAvatar = $this->actingAs($userWithoutAvatar)->get(route('settings.profile.edit'));

        // Assert
        $responseWithAvatar->assertOk();
        $responseWithAvatar->assertSee('画像を削除する');

        $responseWithoutAvatar->assertOk();
        $responseWithoutAvatar->assertDontSee('画像を削除する');
    }
}

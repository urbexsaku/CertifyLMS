<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $user = User::factory()->student()->inProgress()->create();
        $allAnnouncement = Announcement::factory()->create(['title' => '全体お知らせテスト']);
        $certAnnouncement = Announcement::factory()->create([
            'title' => '資格指定お知らせテスト',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);
        $userAnnouncement = Announcement::factory()->create([
            'title' => 'ユーザー指定お知らせテスト',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $user->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $allAnnouncement));
        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertSee('全体お知らせテスト');
        $response->assertSee($admin->name);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $certAnnouncement));
        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertSee('資格指定お知らせテスト');
        $response->assertSee($cert->name);
        $response->assertSee($admin->name);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $userAnnouncement));
        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertSee('ユーザー指定お知らせテスト');
        $response->assertSee($user->name);
        $response->assertSee($admin->name);
    }

    public function test_non_admin_cannot_view_announcement_detail(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create();

        $coachResponse = $this->actingAs($coach)->get(route('admin.announcements.show', $announcement));
        $coachResponse->assertForbidden();

        $studentResponse = $this->actingAs($student)->get(route('admin.announcements.show', $announcement));
        $studentResponse->assertForbidden();
    }
}

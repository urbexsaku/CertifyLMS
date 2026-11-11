<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_history(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertViewIs('announcement.management.index');
        $response->assertViewHas('announcements', function ($announcements) use ($announcement) {
            return $announcements->contains('id', $announcement->id);
        });
    }

    public function test_announcements_are_ordered_by_dispatched_at_desc(): void
    {
        $admin = User::factory()->admin()->create();

        $oldAnnouncement = Announcement::factory()->create([
            'title' => '古いお知らせ',
            'dispatched_at' => Carbon::now()->subHours(5),
        ]);

        $newAnnouncement = Announcement::factory()->create([
            'title' => '新しいお知らせ',
            'dispatched_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertViewIs('announcement.management.index');
        $response->assertSeeInOrder([
            '新しいお知らせ',
            '古いお知らせ',
        ]);
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Announcement::factory()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $announcements = $response->viewData('announcements');
        $this->assertSame(20, $announcements->perPage());
        $this->assertSame(22, $announcements->total());
    }
}

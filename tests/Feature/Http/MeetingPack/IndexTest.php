<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans');
    }

    public function test_student_cannot_access_admin_meeting_packs(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_admin_meeting_packs(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_keyword_filter_matches_name_only(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => 'テスト面談パック']);
        MeetingPack::factory()->published()->create(['name' => '対象外面談パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['keyword' => 'テスト']));

        $response->assertOk();
        $response->assertSee('テスト面談パック');
        $response->assertDontSee('対象外面談パック');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->draft()->create(['name' => 'Draft One']);
        MeetingPack::factory()->published()->create(['name' => 'Published One']);
        MeetingPack::factory()->archived()->create(['name' => 'Archived One']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'published']));

        $response->assertOk();
        $response->assertSee('Published One');
        $response->assertDontSee('Draft One');
        $response->assertDontSee('Archived One');
    }

    public function test_prioritizes_published_then_orders_by_sort_order_and_created_at(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => 'First', 'sort_order' => 0]);
        MeetingPack::factory()->draft()->create(['name' => 'Second', 'sort_order' => 10, 'created_at' => '2026-05-03']);
        MeetingPack::factory()->archived()->create(['name' => 'Third', 'sort_order' => 10, 'created_at' => '2026-05-02']);
        MeetingPack::factory()->draft()->create(['name' => 'Fourth', 'sort_order' => 20]);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'First',
            'Second',
            'Third',
            'Fourth',
        ]);
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }
}

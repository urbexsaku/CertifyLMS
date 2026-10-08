<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans');
    }

    public function test_student_cannot_access_admin_plans(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_admin_plans(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_keyword_filter_matches_name_only(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => 'テストプラン']);
        Plan::factory()->published()->create(['name' => '対象外プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['keyword' => 'テスト']));

        $response->assertOk();
        $response->assertSee('テストプラン');
        $response->assertDontSee('対象外プラン');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->draft()->create(['name' => 'Draft One']);
        Plan::factory()->published()->create(['name' => 'Published One']);
        Plan::factory()->archived()->create(['name' => 'Archived One']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'published']));

        $response->assertOk();
        $response->assertSee('Published One');
        $response->assertDontSee('Draft One');
        $response->assertDontSee('Archived One');
    }

    public function test_prioritizes_published_then_orders_by_sort_order_and_created_at(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => 'First', 'sort_order' => 0]);
        Plan::factory()->draft()->create(['name' => 'Second', 'sort_order' => 10, 'created_at' => '2026-05-03']);
        Plan::factory()->archived()->create(['name' => 'Third', 'sort_order' => 10, 'created_at' => '2026-05-02']);
        Plan::factory()->draft()->create(['name' => 'Fourth', 'sort_order' => 20]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

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
        Plan::factory()->published()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }
}

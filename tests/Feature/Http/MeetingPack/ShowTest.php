<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_and_purchase_history(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create([
            'name' => '追加面談3回パック',
            'meeting_count' => 3,
            'price' => 12000,
        ]);

        $pendingUser = User::factory()->student()->create([
            'name' => '決済待ちユーザー',
        ]);
        $succeededUser = User::factory()->student()->create([
            'name' => '決済完了ユーザー',
        ]);
        $failedUser = User::factory()->student()->create([
            'name' => '決済失敗ユーザー',
        ]);

        Payment::factory()->pending()->create([
            'meeting_pack_id' => $pack->id,
            'user_id' => $pendingUser->id,
            'amount' => 12000,
            'quantity' => 3,
        ]);

        Payment::factory()->succeeded()->create([
            'meeting_pack_id' => $pack->id,
            'user_id' => $succeededUser->id,
            'amount' => 12000,
            'quantity' => 3,
        ]);

        Payment::factory()->failed()->create([
            'meeting_pack_id' => $pack->id,
            'user_id' => $failedUser->id,
            'amount' => 12000,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.show');

        $response->assertSee('追加面談3回パック');
        $response->assertSee('購入履歴(直近 20 件)');

        $response->assertSee('決済待ちユーザー');
        $response->assertSee('決済完了ユーザー');
        $response->assertSee('決済失敗ユーザー');

        $response->assertSee('¥12,000');
        $response->assertSee('決済待ち');
        $response->assertSee('決済完了');
        $response->assertSee('決済失敗');
    }

    public function test_admin_can_view_meeting_pack_without_purchase_history(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk();
        $response->assertSee('この SKU の購入はまだありません。');
    }

    public function test_student_cannot_view_meeting_pack_detail(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($student)
            ->get(route('admin.meeting-packs.show', $pack))
            ->assertForbidden();
    }

    public function test_coach_cannot_view_meeting_pack_detail(): void
    {
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($coach)
            ->get(route('admin.meeting-packs.show', $pack))
            ->assertForbidden();
    }

    public function test_purchase_history_displays_latest_20_payments(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        for ($i = 0; $i < 21; $i++) {
            $buyer = User::factory()->student()->create([
                'name' => "購入者 {$i}",
            ]);

            Payment::factory()->pending()->create([
                'meeting_pack_id' => $pack->id,
                'user_id' => $buyer->id,
                'created_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk();

        // 新しい20件が表示される。
        $response->assertSee('購入者 0');
        $response->assertSee('購入者 19');

        // 21件目の古い購入履歴は表示されない。
        $response->assertDontSee('購入者 20');
    }
}

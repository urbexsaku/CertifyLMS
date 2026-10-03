<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create([
            'name' => 'Old Name',
        ]);

        $payload = [
            'name' => 'New Name',
            'description' => '更新後の説明',
            'meeting_count' => $pack->meeting_count,
            'price' => $pack->price,
        ];

        $response = $this->actingAs($admin)->put(route('admin.meeting-packs.update', $pack), $payload);

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'name' => 'New Name',
            'description' => '更新後の説明',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_is_unchanged_even_if_payload_includes_status(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        $payload = [
            'name' => $pack->name,
            'description' => $pack->description,
            'meeting_count' => $pack->meeting_count,
            'price' => $pack->price,
            'status' => 'draft',
        ];

        $this->actingAs($admin)->put(route('admin.meeting-packs.update', $pack), $payload);

        $this->assertSame('published', $pack->fresh()->status->value);
    }

    public function test_coach_cannot_update(): void
    {
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($coach)->put(route('admin.meeting-packs.update', $pack), [
            'name' => 'Hack',
            'description' => '更新後の説明',
            'meeting_count' => $pack->meeting_count,
            'price' => $pack->price,
        ]);

        $response->assertForbidden();
    }
}

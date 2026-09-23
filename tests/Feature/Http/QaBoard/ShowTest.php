<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_thread_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $publishedCert = Certification::factory()->published()->create();
        $draftCert = Certification::factory()->draft()->create();
        $archivedCert = Certification::factory()->archived()->create();

        $publishedThread = QaThread::factory()->for($publishedCert)->create();
        $draftThread = QaThread::factory()->for($draftCert)->create();
        $archivedThread = QaThread::factory()->for($archivedCert)->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $publishedThread));
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $draftThread));
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $archivedThread));
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
    }

    public function test_student_can_view_thread_detail(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
    }

    public function test_student_gets_403_on_non_published_thread(): void
    {
        $student = User::factory()->student()->create();
        $draftCert = Certification::factory()->draft()->create();
        $archivedCert = Certification::factory()->archived()->create();

        $draftThread = QaThread::factory()->for($draftCert)->create();
        $archivedThread = QaThread::factory()->for($archivedCert)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $draftThread));
        $response->assertForbidden();

        $response = $this->actingAs($student)->get(route('qa-board.show', $archivedThread));
        $response->assertForbidden();
    }

    public function test_unassigned_coach_gets_403_on_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $thread));
        $response->assertForbidden();
    }
}

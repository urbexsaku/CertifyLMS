<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resolve_and_unresolve_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $openThread = QaThread::factory()->open()->forUser($student)->create();
        $otherOpenThread = QaThread::factory()->open()->forUser($student)->create();
        $resolvedThread = QaThread::factory()->resolved()->forUser($student)->create();

        $this->actingAs($student)
            ->post(route('qa-board.resolve', $openThread))
            ->assertRedirect();
        $openThread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $openThread->status);

        $this->actingAs($student)
            ->post(route('qa-board.unresolve', $resolvedThread))
            ->assertRedirect();
        $resolvedThread->refresh();
        $this->assertSame(QaThreadStatus::Open, $resolvedThread->status);

        $this->actingAs($coach)
            ->post(route('qa-board.resolve', $otherOpenThread))
            ->assertForbidden();
        $otherOpenThread->refresh();
        $this->assertSame(QaThreadStatus::Open, $otherOpenThread->status);

        $this->actingAs($admin)
            ->post(route('qa-board.resolve', $otherOpenThread))
            ->assertForbidden();
        $otherOpenThread->refresh();
        $this->assertSame(QaThreadStatus::Open, $otherOpenThread->status);
    }
}

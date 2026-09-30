<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\UseCases\QaThread\ResolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThread スレッド解決 Action `ResolveAction` の 状態遷移を検証する Feature テスト。
 */
class ResolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_changes_open_to_resolved_and_sets_resolved_at(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();

        // Act
        $result = (new ResolveAction)($thread);

        // Assert
        $this->assertSame(QaThreadStatus::Resolved, $result->status);
        $this->assertNotNull($result->resolved_at);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }
}

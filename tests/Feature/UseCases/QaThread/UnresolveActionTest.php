<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\UseCases\QaThread\UnresolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThread スレッド未解決化 Action `UnresolveAction` の 状態遷移を検証する Feature テスト。
 */
class UnresolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolve_changes_resolved_to_open_and_clears_resolved_at(): void
    {
        // Arrange
        $thread = QaThread::factory()->create(['status' => QaThreadStatus::Resolved->value]);

        // Act
        $result = (new UnresolveAction)($thread);

        // Assert
        $this->assertSame(QaThreadStatus::Open, $result->status);
        $this->assertNull($result->resolved_at);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Open->value,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PlanStatus;
use Tests\TestCase;

class PlanStatusTest extends TestCase
{
    public function test_enum_lists_three_status_values(): void
    {
        $values = array_map(fn (PlanStatus $s) => $s->value, PlanStatus::cases());

        $this->assertEqualsCanonicalizing(
            ['draft', 'published', 'archived'],
            $values,
        );
    }

    public function test_japanese_labels(): void
    {
        $this->assertSame('下書き', PlanStatus::Draft->label());
        $this->assertSame('公開中', PlanStatus::Published->label());
        $this->assertSame('アーカイブ', PlanStatus::Archived->label());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 質問掲示板スレッド一覧 IndexRequest の rules() を検証する Unit テスト。
 * filter (keyword / certification_id (exists) / status) の nullable 検証を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_status(): void
    {
        $validator = Validator::make(['status' => QaThreadStatus::Resolved->value], (new IndexRequest()->rules()));
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_status_invalid(): void
    {
        $validator = Validator::make(['status' => 'unknown'], (new IndexRequest()->rules()));
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_fails_when_keyword_exceeds_max(): void
    {
        $validator = Validator::make(['keyword' => str_repeat('a', 101)], (new IndexRequest)->rules());
        $this->assertArrayHasKey('keyword', $validator->errors()->toArray());
    }

    public function test_fails_when_certification_id_not_exists(): void
    {
        $validator = Validator::make(['certification_id' => (string) Str::ulid()], (new IndexRequest)->rules());
        $this->assertArrayHasKey('certification_id', $validator->errors()->toArray());
    }
}

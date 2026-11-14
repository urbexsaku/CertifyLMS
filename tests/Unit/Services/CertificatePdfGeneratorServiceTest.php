<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Services\CertificatePdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CertificatePdfGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_and_stores_it_on_private_disk(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create();

        app(CertificatePdfGeneratorService::class)->generate($certificate);

        $disk = Storage::disk('private');

        $this->assertTrue($disk->exists($certificate->pdf_path));
        $this->assertStringStartsWith('%PDF-', $disk->get($certificate->pdf_path));
    }

    public function test_throws_exception_when_pdf_cannot_be_saved(): void
    {
        $certificate = Certificate::factory()->create();

        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->with($certificate->pdf_path, Mockery::type('string'))->andReturn(false);

        Storage::shouldReceive('disk')->once()->with('private')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('修了証PDFの保存に失敗しました。');

        app(CertificatePdfGeneratorService::class)->generate($certificate);
    }
}

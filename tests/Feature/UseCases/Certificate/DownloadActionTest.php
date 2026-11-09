<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Models\Certificate;
use App\UseCases\Certificate\DownloadAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class DownloadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_existing_pdf_as_attachment_with_certificate_id_filename(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create();
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-test');

        $response = app(DownloadAction::class)->handle($certificate);

        $this->assertSame(
            'attachment; filename=certificate-'.$certificate->id.'.pdf',
            $response->headers->get('Content-Disposition')
        );

        $this->assertSame(
            'application/pdf',
            $response->headers->get('Content-Type')
        );
    }

    public function test_returns_404_when_pdf_file_does_not_exist(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create();

        $action = $this->app->make(DownloadAction::class);

        try {
            $action->handle($certificate);
            $this->fail('404例外が発生しませんでした。');
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}

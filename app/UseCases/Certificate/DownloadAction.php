<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadAction
{
    public function handle(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk('private');

        abort_unless($disk->exists($certificate->pdf_path), 404);

        return $disk->download(
            $certificate->pdf_path,
            'certificate-'.$certificate->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}

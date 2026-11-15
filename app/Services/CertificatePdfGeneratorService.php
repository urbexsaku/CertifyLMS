<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * 修了証PDFの生成とprivate diskへの保存を担当するサービス。
 */
class CertificatePdfGeneratorService
{
    /**
     * 修了証用 Bladeを mPDF でレンダリングし、Certificate の pdf_path に private disk 保存する。
     *
     * @param Certificate $certificate PDFを生成する修了証
     *
     * @throws \RuntimeException PDFの保存に失敗した場合
     */
    public function generate(Certificate $certificate): void
    {
        $pdf = new Mpdf([
            'format' => 'A4',
            'mode' => 'ja',
        ]);

        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $pdf->WriteHTML($html);

        $saved = Storage::disk('private')->put(
            $certificate->pdf_path,
            $pdf->Output('', 'S')
        );

        if (! $saved) {
            throw new \RuntimeException('修了証PDFの保存に失敗しました。');
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 修了証PDFダウンロードの Controller を検証する Feature テスト。
 * 認証・認可および修了済み受講生によるダウンロードを確認する。
 */
class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduated_student_can_download_own_certificate(): void
    {
        Storage::fake('private');

        $graduatedStudent = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($graduatedStudent)->passed()->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-test');

        $response = $this->actingAs($graduatedStudent)->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertDownload('certificate-'.$certificate->id.'.pdf');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->get(route('certificates.download', $certificate));

        $response->assertRedirect(route('login'));
    }

    public function test_forbidden_user_cannot_download_certificate(): void
    {
        $certificate = Certificate::factory()->create();
        $otherStudent = User::factory()->student()->create();

        $response = $this->actingAs($otherStudent)->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }
}

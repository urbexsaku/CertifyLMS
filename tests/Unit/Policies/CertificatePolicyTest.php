<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CertificatePolicy の修了証ダウンロード権限を検証する Unit テスト。
 * admin 全可 / student 本人のみ / coach 担当資格のみ。
 */
class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    private CertificatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CertificatePolicy;
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();

        $this->assertTrue($this->policy->download($admin, $certificate));
    }

    public function test_student_can_download_own_certificate(): void
    {
        $certificate = Certificate::factory()->create();

        $this->assertTrue(
            $this->policy->download($certificate->user, $certificate)
        );
    }

    public function test_student_cannot_download_other_students_certificate(): void
    {
        $certificate = Certificate::factory()->create();
        $otherStudent = User::factory()->student()->create();

        $this->assertFalse(
            $this->policy->download($otherStudent, $certificate)
        );
    }

    public function test_assigned_coach_can_download_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certificate = Certificate::factory()->create();

        $certificate->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue(
            $this->policy->download($coach, $certificate)
        );
    }

    public function test_unassigned_coach_cannot_download_certificate(): void
    {
        $coach = User::factory()->coach()->create();
        $certificate = Certificate::factory()->create();

        $this->assertFalse(
            $this->policy->download($coach, $certificate)
        );
    }
}

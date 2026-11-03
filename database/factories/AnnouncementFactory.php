<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory()->admin(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(2, true),
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ];
    }

    public function forCertification(): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => Certification::factory()->published(),
        ]);
    }

    public function forUser(): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => User::factory()->student()->inProgress(),
        ]);
    }
}

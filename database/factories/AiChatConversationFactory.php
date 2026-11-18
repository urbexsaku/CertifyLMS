<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatConversation>
 */
class AiChatConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'enrollment_id' => Enrollment::factory(),
            'section_id' => null,
            'title' => fake()->sentence(3),
        ];
    }

    /**
     * Section に紐づく会話を作成する。
     */
    public function forSection(
        Section $section,
        Enrollment $enrollment,
    ): static {
        return $this->state(fn (): array => [
            'section_id' => $section->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }
}

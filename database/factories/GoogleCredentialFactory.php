<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCredential>
 */
class GoogleCredentialFactory extends Factory
{
    protected $model = GoogleCredential::class;

    public function definition(): array
    {
        $refreshToken = 'dummy-refresh-token-'.fake()->uuid();

        return [
            'user_id' => User::factory()->coach(),
            'google_user_id' => fake()->uuid(),
            'calendar_id' => 'primary',

            // Google\Client::setAccessToken() に渡す token response と同じJSON形式。
            // ダミー値のため実Google API通信には使用できない。
            'access_token' => json_encode([
                'access_token' => 'dummy-access-token-'.fake()->uuid(),
                'refresh_token' => $refreshToken,
                'expires_in' => 3600,
                'created' => now()->timestamp,
            ], JSON_THROW_ON_ERROR),

            'refresh_token' => $refreshToken,
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ];
    }

    /**
     * 期限切れトークン状態。
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
            'access_token' => json_encode([
                'access_token' => 'dummy-expired-access-token',
                'refresh_token' => 'dummy-refresh-token',
                'expires_in' => 3600,
                'created' => now()->subHours(2)->timestamp,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}

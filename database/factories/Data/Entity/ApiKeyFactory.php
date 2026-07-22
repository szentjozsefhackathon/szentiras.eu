<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rawKey = Str::uuid()->toString();

        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'key_hash' => Hash::make($rawKey),
            'key_plain' => null,
            'key_prefix' => substr($rawKey, 0, 8),
            'is_internal' => false,
            'is_self_service' => false,
            'throttle_rate' => null,
            'enabled' => true,
            'created_by_anonymous_id' => AnonymousId::factory(),
            'usage_count' => 0,
        ];
    }

    /**
     * A key created by a user through the self-service flow (raw key retained).
     */
    public function selfService(): static
    {
        return $this->state(function (): array {
            $rawKey = Str::uuid()->toString();

            return [
                'key_hash' => Hash::make($rawKey),
                'key_plain' => $rawKey,
                'key_prefix' => substr($rawKey, 0, 8),
                'is_self_service' => true,
            ];
        });
    }
}

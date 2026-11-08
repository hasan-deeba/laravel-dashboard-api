<?php

namespace Database\Factories\Clients;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Models\Clients\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Clients\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => fake()->optional()->company(),
            'age' => fake()->numberBetween(18, 70),
            'gender' => fake()->randomElement(GenderType::cases()),
            'marital_status' => fake()->randomElement(MaritalStatusType::cases()),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the client has not verified the OTP yet.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

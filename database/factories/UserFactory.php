<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->userName() . '@example.com',
            'asal_kampus' => fake()->randomElement([
                'Politeknik Negeri Padang',
                'Universitas Andalas',
                'Universitas Negeri Padang',
                'Institut Teknologi Padang',
                'Universitas Gadjah Mada',
                'Institut Teknologi Bandung',
            ]),
            'role' => 'pembeli',
            'email_verified_at' => now(),
            'password' => static::$password ??= bcrypt('password'),
            'no_telepon' => fake()->numerify('08##########'),
            'aktif' => true,
            'latitude' => fake()->latitude(-0.95, -0.85),
            'longitude' => fake()->longitude(100.30, 100.45),
            'identitas_terverifikasi' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function penjual(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'penjual',
        ]);
    }
}

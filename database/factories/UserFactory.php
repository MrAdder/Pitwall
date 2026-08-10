<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Platform administrators.
 *
 * No password is generated: sign-in is delegated to Microsoft Entra ID, and a
 * factory that quietly created usable local passwords would make it easy to
 * write a test that passes against an authentication path production does not
 * have.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'microsoft_object_id' => fake()->uuid(),
            'microsoft_tenant_id' => fake()->uuid(),
            'password' => null,
        ];
    }
}

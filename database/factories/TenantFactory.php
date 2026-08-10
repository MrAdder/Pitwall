<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'microsoft_tenant_id' => fake()->uuid(),
            'default_domain' => Str::slug($name).'.onmicrosoft.com',
            'status' => TenantStatus::Active,
            'consented_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => TenantStatus::Pending, 'consented_at' => null]);
    }

    public function disabled(): self
    {
        return $this->state(['status' => TenantStatus::Disabled]);
    }
}

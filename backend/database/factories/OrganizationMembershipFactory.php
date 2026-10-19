<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role_id' => fn (): int => Role::query()->firstOrCreate(
                ['slug' => 'surveyor'],
                ['name' => 'Surveyor', 'scope' => 'organization', 'is_system' => true],
            )->getKey(),
            'status' => 'active',
        ];
    }
}

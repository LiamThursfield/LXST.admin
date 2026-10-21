<?php

namespace Database\Factories;

use App\Models\RolePermissionExclusion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<RolePermissionExclusion>
 */
class RolePermissionExclusionFactory extends Factory
{
    protected $model = RolePermissionExclusion::class;

    /**
     * Define the model's default state (a permission a role is kept from having).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::query()->inRandomOrder()->value('id') ?? Role::create(['name' => fake()->unique()->word()])->id,
            'permission_id' => fn () => Permission::query()->inRandomOrder()->value('id') ?? Permission::create(['name' => fake()->unique()->word()])->id,
        ];
    }
}

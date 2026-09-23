<?php

use App\Models\User;
use Modules\Organization\Models\Entity;
use Modules\Users\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-M', 'name' => 'Entity M', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'admin.perm@test.local']);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'staff.perm@test.local']);
    $this->staff->assignRole('staff');
});

it('lists the permission vocabulary for any authenticated user', function () {
    $names = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'guardName']]])
        ->json('data.*.name');

    expect($names)
        ->toHaveCount(count(PermissionSeeder::PERMISSIONS))
        ->toBe(collect(PermissionSeeder::PERMISSIONS)->sort()->values()->all());
});

it('requires authentication for the permission list', function () {
    $this->getJson('/api/v1/permissions')->assertStatus(401);
});

it('creates a role with selected permissions as admin', function () {
    $payload = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/roles', [
            'name' => 'cataloger',
            'permissions' => ['brands.view', 'brands.manage'],
        ])
        ->assertStatus(201)
        ->json('data.permissions');

    expect(collect($payload)->sort()->values()->all())->toBe(['brands.manage', 'brands.view']);

    $role = Role::findByName('cataloger', 'sanctum');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['brands.manage', 'brands.view']);
});

it('replaces the previous permission set when updating a role', function () {
    $role = Role::findOrCreate('editor', 'sanctum');
    $role->givePermissionTo(['brands.view', 'brands.manage']);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->getKey()}", ['permissions' => ['products.view']])
        ->assertOk()
        ->assertJsonPath('data.permissions', ['products.view']);

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['products.view']);
});

it('rejects unknown permission names with the 422 envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'ghost', 'permissions' => ['not.a.permission']])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);

    expect(Role::query()->where('name', 'ghost')->exists())->toBeFalse();
});

it('rejects permissions registered on another guard', function () {
    Permission::findOrCreate('legacy.manage', 'web');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'legacy', 'permissions' => ['legacy.manage']])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);
});

it('grants access exactly according to the role permissions', function () {
    $brandEditor = Role::findOrCreate('brand-editor', 'sanctum');
    $brandEditor->givePermissionTo(['brands.view', 'brands.manage']);

    $editor = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'editor@test.local']);
    $editor->assignRole('brand-editor');

    $this->actingAs($editor, 'sanctum')
        ->postJson('/api/v1/products/brands', ['name' => 'Acme'])
        ->assertStatus(201);

    $this->actingAs($editor, 'sanctum')
        ->getJson('/api/v1/products/brands')
        ->assertOk();

    $this->actingAs($editor, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertStatus(403);

    $this->actingAs($editor, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'X',
            'email' => 'x@test.local',
            'password' => 'secret-password',
        ])
        ->assertStatus(403);

    $this->actingAs($editor, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'sneaky'])
        ->assertStatus(403);
});

it('denies reads for a role without view permissions', function () {
    $emptyRole = Role::findOrCreate('empty-role', 'sanctum');

    $bare = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'bare@test.local']);
    $bare->assignRole('empty-role');

    $this->actingAs($bare, 'sanctum')->getJson('/api/v1/products/brands')->assertStatus(403);
    $this->actingAs($bare, 'sanctum')->getJson('/api/v1/users')->assertStatus(403);
    $this->actingAs($bare, 'sanctum')->getJson('/api/v1/roles')->assertStatus(403);
});

it('forbids staff from granting permissions when updating roles', function () {
    $role = Role::findOrCreate('auditor', 'sanctum');

    $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->getKey()}", ['permissions' => ['users.manage']])
        ->assertStatus(403);

    expect($role->refresh()->permissions)->toBeEmpty();
});

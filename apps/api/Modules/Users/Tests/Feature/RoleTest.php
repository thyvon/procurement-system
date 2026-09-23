<?php

use App\Models\User;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-R', 'name' => 'Entity R', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'admin.role@test.local']);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey(), 'email' => 'staff.role@test.local']);
    $this->staff->assignRole('staff');
});

it('lists roles for any authenticated user', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.*.name', ['admin', 'staff']);
});

it('forbids non-admins from creating roles', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'manager'])
        ->assertStatus(403);

    expect(Role::query()->where('name', 'manager')->exists())->toBeFalse();
});

it('creates a role with the sanctum guard as admin', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'manager'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'manager')
        ->assertJsonPath('data.guardName', 'sanctum');

    expect(Role::query()->where('name', 'manager')->where('guard_name', 'sanctum')->exists())->toBeTrue();
});

it('rejects a duplicate role name with a 422 envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/roles', ['name' => 'staff'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);
});

it('renames a role as admin', function () {
    $role = Role::findOrCreate('auditor', 'sanctum');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->getKey()}", ['name' => 'reviewer'])
        ->assertOk()
        ->assertJsonPath('data.name', 'reviewer');
});

it('prevents renaming the system admin role', function () {
    $role = Role::findByName('admin', 'sanctum');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->getKey()}", ['name' => 'superuser'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);

    expect($role->refresh()->name)->toBe('admin');
});

it('prevents deleting the system admin role', function () {
    $role = Role::findByName('admin', 'sanctum');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->getKey()}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);

    expect(Role::query()->where('name', 'admin')->exists())->toBeTrue();
});

it('prevents deleting a role still assigned to users', function () {
    $role = Role::findOrCreate('auditor', 'sanctum');
    $this->staff->assignRole('auditor');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->getKey()}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);

    expect(Role::query()->where('name', 'auditor')->exists())->toBeTrue()
        ->and($this->staff->refresh()->hasRole('auditor'))->toBeTrue();
});

it('deletes an unassigned role', function () {
    $role = Role::findOrCreate('auditor', 'sanctum');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(Role::query()->where('name', 'auditor')->exists())->toBeFalse();
});

it('forbids non-admins from updating or deleting roles', function () {
    $role = Role::findOrCreate('auditor', 'sanctum');

    $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->getKey()}", ['name' => 'renamed'])
        ->assertStatus(403);

    $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->getKey()}")
        ->assertStatus(403);

    expect($role->refresh()->name)->toBe('auditor');
});

it('requires authentication for role routes', function () {
    $this->getJson('/api/v1/roles')->assertStatus(401);
    $this->postJson('/api/v1/roles', ['name' => 'x'])->assertStatus(401);
});

<?php

namespace Modules\Organization\Tests\Feature;

use App\Models\User;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-O', 'name' => 'Entity O', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-R', 'name' => 'Entity R', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

it('creates, updates and soft-deletes a campus as admin', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Main Campus', 'is_active' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Main Campus')
        ->assertJsonPath('data.isActive', true);

    $campusId = $created->json('data.id');

    $this->patchJson("/api/v1/organization/campuses/{$campusId}", ['name' => 'North Campus', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'North Campus')
        ->assertJsonPath('data.isActive', false);

    $this->deleteJson("/api/v1/organization/campuses/{$campusId}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(Campus::withTrashed()->find($campusId)->deleted_at)->not->toBeNull();
});

it('derives a short code from the name when the client omits one', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Main Campus'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'MAIN CAMPU');
});

it('keeps an explicit short code on create and update', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Head Office', 'short_code' => 'HQ'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'HQ');

    $this->patchJson("/api/v1/organization/campuses/{$created->json('data.id')}", ['short_code' => 'HDO'])
        ->assertOk()
        ->assertJsonPath('data.shortCode', 'HDO');
});

it('rejects a short code already taken in the same entity', function () {
    Campus::create(['name' => 'Taken', 'entity_id' => $this->entity->getKey(), 'short_code' => 'TAKEN']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Another', 'short_code' => 'TAKEN'])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors'])
        ->assertJsonStructure(['errors' => ['short_code']]);

    expect(Campus::query()->where('name', 'Another')->exists())->toBeFalse();
});

it('allows the same short code in another entity', function () {
    Campus::create(['name' => 'Foreign', 'entity_id' => $this->otherEntity->getKey(), 'short_code' => 'TWIN']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Local Twin', 'short_code' => 'TWIN'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'TWIN');
});

it('rejects a short code longer than sixteen characters', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Too Long', 'short_code' => str_repeat('X', 17)])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['short_code']]);
});

it('frees the short code on soft delete', function () {
    $first = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Main Campus', 'short_code' => 'MAIN CAMPU'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'MAIN CAMPU');

    $this->deleteJson("/api/v1/organization/campuses/{$first->json('data.id')}")->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Satellite Campus', 'short_code' => 'MAIN CAMPU'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'MAIN CAMPU');
});

it('forbids staff from creating campuses', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => 'Nope'])
        ->assertStatus(403);
});

it('rejects an over-length name with the standard 422 envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses', ['name' => str_repeat('a', 65)])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('creates a division with departments and echoes departmentIds', function () {
    $departments = Department::factory()->count(2)->create(['entity_id' => $this->entity->getKey()]);

    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/divisions', [
            'name' => 'Finance',
            'department_ids' => $departments->pluck('id')->values()->all(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Finance')
        ->assertJsonPath('data.shortCode', 'FINANCE');

    $divisionId = $created->json('data.id');

    expect($created->json('data.departmentIds'))->toEqualCanonicalizing($departments->pluck('id')->all());

    $this->getJson("/api/v1/organization/divisions/{$divisionId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Finance');

    $this->patchJson("/api/v1/organization/divisions/{$divisionId}", ['department_ids' => [$departments->first()->getKey()]])
        ->assertOk();

    expect(
        Division::with('departments:id')->find($divisionId)->departments->pluck('id')->all(),
    )->toEqualCanonicalizing([$departments->first()->getKey()]);
});

it('rejects department_ids that do not exist', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/divisions', [
            'name' => 'Ghost',
            'department_ids' => ['01JXXXXXXXXXXXXXXXXXXXXXXX'],
        ])
        ->assertStatus(422);
});

it('creates a department with divisions and echoes divisionIds', function () {
    $divisions = Division::factory()->count(2)->create(['entity_id' => $this->entity->getKey()]);

    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/departments', [
            'name' => 'Accounting',
            'division_ids' => $divisions->pluck('id')->values()->all(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Accounting')
        ->assertJsonPath('data.shortCode', 'ACCOUNTING');

    $departmentId = $created->json('data.id');

    expect($created->json('data.divisionIds'))->toEqualCanonicalizing($divisions->pluck('id')->all());

    $this->patchJson("/api/v1/organization/departments/{$departmentId}", ['division_ids' => [$divisions->first()->getKey()]])
        ->assertOk();

    expect(
        Department::with('divisions:id')->find($departmentId)->divisions->pluck('id')->all(),
    )->toEqualCanonicalizing([$divisions->first()->getKey()]);
});

it('requires division_ids when creating a department', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/departments', ['name' => 'Orphan'])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('lists departments without exposing other entities', function () {
    Department::create(['name' => 'Local Dept', 'entity_id' => $this->entity->getKey()]);
    Department::create(['name' => 'Foreign Dept', 'entity_id' => $this->otherEntity->getKey()]);

    $names = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/organization/departments')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toContain('Local Dept')
        ->and($names)->not->toContain('Foreign Dept');
});

it('requires authentication for organization lookups', function () {
    $this->getJson('/api/v1/organization/campuses')->assertStatus(401);
});

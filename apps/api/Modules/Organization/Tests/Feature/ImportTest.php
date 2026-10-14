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

    $this->entity = Entity::create(['code' => 'ENT-I', 'name' => 'Entity I', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

it('downloads a campus csv template with headers and examples', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/organization/campuses/import/template')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('name,status')
        ->and($response->getContent())->toContain('Main Campus');
});

it('downloads a department csv template with headers and examples', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/organization/departments/import/template')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('name,divisions,status')
        ->and($response->getContent())->toContain('Finance,HR');
});

it('imports valid campus rows and reports per-row errors for bad ones', function () {
    Campus::create(['name' => 'Existing Campus', 'entity_id' => $this->entity->getKey()]);

    $rows = [
        ['name' => 'Main Campus', 'status' => 'active'],
        ['name' => '', 'status' => 'active'],
        ['name' => 'Main Campus', 'status' => 'active'],
        ['name' => 'Existing Campus', 'status' => 'active'],
        ['name' => 'Archived Campus', 'status' => 'inactive'],
    ];

    $result = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses/import', ['rows' => $rows])
        ->assertOk()
        ->json('data');

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(3)
        ->and($result['errors'])->toHaveCount(3);

    expect(Campus::query()->where('name', 'Main Campus')->where('entity_id', $this->entity->getKey())->exists())->toBeTrue()
        ->and(Campus::query()->where('name', 'Archived Campus')->first()->is_active)->toBeFalse();
});

it('imports department rows and links divisions by name', function () {
    $finance = Division::create(['name' => 'Finance', 'entity_id' => $this->entity->getKey()]);
    $hr = Division::create(['name' => 'HR', 'entity_id' => $this->entity->getKey()]);

    $rows = [
        ['name' => 'Accounting', 'divisions' => 'Finance,HR', 'status' => 'active'],
        ['name' => 'No Division', 'divisions' => '', 'status' => 'active'],
        ['name' => 'Ghost Link', 'divisions' => 'Nope', 'status' => 'active'],
    ];

    $result = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/departments/import', ['rows' => $rows])
        ->assertOk()
        ->json('data');

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(2)
        ->and($result['errors'])->toHaveCount(2);

    $department = Department::query()->with('divisions:id')->where('name', 'Accounting')->first();
    expect($department->divisions->pluck('id')->all())
        ->toEqualCanonicalizing([$finance->getKey(), $hr->getKey()]);
});

it('rejects an empty import payload with the standard 422 envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/organization/campuses/import', ['rows' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('forbids staff from importing', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/organization/campuses/import', ['rows' => [['name' => 'X']]])
        ->assertStatus(403);
});

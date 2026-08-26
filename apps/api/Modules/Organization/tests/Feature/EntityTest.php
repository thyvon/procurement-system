<?php

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entityA = Entity::create([
        'code' => 'ENT-A',
        'name' => 'Entity A',
        'timezone' => 'UTC',
        'locale' => 'en',
    ]);

    $this->entityB = Entity::create([
        'code' => 'ENT-B',
        'name' => 'Entity B',
        'timezone' => 'UTC',
        'locale' => 'en',
    ]);

    $this->admin = User::factory()->create(['entity_id' => $this->entityA->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entityA->getKey()]);
    $this->staff->assignRole('staff');
});

function actingAs(User $user): void
{
    test()->actingAs($user, 'sanctum');
}

it('lists entities for authenticated users', function () {
    actingAs($this->staff);

    $this->getJson('/api/v1/entities')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/entities')->assertStatus(401);
});

it('creates an entity as admin', function () {
    actingAs($this->admin);

    $id = $this->postJson('/api/v1/entities', [
        'code' => 'ENT-C',
        'name' => 'Entity C',
        'timezone' => 'Asia/Phnom_Penh',
        'locale' => 'km',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.code', 'ENT-C')
        ->json('data.id');

    expect(Str::isUlid($id))->toBeTrue()
        ->and(Entity::query()->where('code', 'ENT-C')->exists())->toBeTrue();
});

it('forbids entity creation for non-admin roles', function () {
    actingAs($this->staff);

    $this->postJson('/api/v1/entities', [
        'code' => 'ENT-D',
        'name' => 'Entity D',
        'timezone' => 'UTC',
        'locale' => 'en',
    ])->assertStatus(403);
});

it('rejects duplicate codes and invalid timezones with the standard envelope', function () {
    actingAs($this->admin);

    $response = $this->postJson('/api/v1/entities', [
        'code' => 'ENT-A',
        'name' => 'Dup',
        'timezone' => 'Not/AZone',
        'locale' => 'en',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);
});

it('updates an entity as admin', function () {
    actingAs($this->admin);

    $this->patchJson("/api/v1/entities/{$this->entityB->getKey()}", [
        'name' => 'Entity B Renamed',
    ])->assertOk()->assertJsonPath('data.name', 'Entity B Renamed');
});

it('soft-deletes an entity as admin', function () {
    actingAs($this->admin);

    $this->deleteJson("/api/v1/entities/{$this->entityB->getKey()}")->assertOk();

    expect($this->entityB->refresh()->deleted_at)->not->toBeNull();
});

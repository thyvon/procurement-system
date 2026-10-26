<?php

use App\Models\User;
use Modules\EmployeeProfiles\Models\EmployeeProfile;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;
use Modules\Organization\Models\Entity;

beforeEach(function () {
    $this->entityA = Entity::create(['code' => 'ENT-EPA', 'name' => 'Entity EPA', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->entityB = Entity::create(['code' => 'ENT-EPB', 'name' => 'Entity EPB', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->adminA = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'admin.epa@test.local']);
    $this->adminA->assignRole('admin');

    $this->adminB = User::factory()->create(['entity_id' => $this->entityB->getKey(), 'email' => 'admin.epb@test.local']);
    $this->adminB->assignRole('admin');

    $this->staffA = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'staff.epa@test.local']);
    $this->staffA->assignRole('staff');

    $this->peerA = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'peer.epa@test.local']);
    $this->peerA->assignRole('staff');

    $this->staffB = User::factory()->create(['entity_id' => $this->entityB->getKey(), 'email' => 'staff.epb@test.local']);
    $this->staffB->assignRole('staff');
});

it('returns a null current profile when the user has none', function () {
    $response = $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffA->getKey()}/employee-profile")
        ->assertOk();

    expect($response->json('data'))->toBeNull();
});

it('creates the current profile and exposes it through the user resource', function () {
    $campus = Campus::factory()->create(['entity_id' => $this->entityA->getKey()]);
    $division = Division::factory()->create(['entity_id' => $this->entityA->getKey()]);
    $department = Department::factory()->create(['entity_id' => $this->entityA->getKey()]);

    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-1001',
            'position' => 'Procurement Officer',
            'campus_id' => $campus->getKey(),
            'division_id' => $division->getKey(),
            'department_id' => $department->getKey(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.userId', $this->staffA->getKey())
        ->assertJsonPath('data.cardId', 'CARD-1001')
        ->assertJsonPath('data.position', 'Procurement Officer')
        ->assertJsonPath('data.campusId', $campus->getKey())
        ->assertJsonPath('data.campusName', $campus->name)
        ->assertJsonPath('data.divisionName', $division->name)
        ->assertJsonPath('data.departmentName', $department->name)
        ->assertJsonPath('data.isCurrent', true);

    $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffA->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.cardId', 'CARD-1001')
        ->assertJsonPath('data.position', 'Procurement Officer')
        ->assertJsonPath('data.campusId', $campus->getKey())
        ->assertJsonPath('data.campusName', $campus->name)
        ->assertJsonPath('data.divisionName', $division->name)
        ->assertJsonPath('data.departmentName', $department->name);
});

it('keeps a history and closes the previous version when the profile changes', function () {
    $campus = Campus::factory()->create(['entity_id' => $this->entityA->getKey()]);

    $first = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-2002',
            'position' => 'Officer',
            'campus_id' => $campus->getKey(),
        ])
        ->assertStatus(201)
        ->json('data.id');

    $second = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'position' => 'Head of Procurement',
            'effective_from' => '2026-10-01',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 'Head of Procurement')
        ->assertJsonPath('data.cardId', 'CARD-2002')
        ->assertJsonPath('data.campusId', $campus->getKey())
        ->assertJsonPath('data.effectiveFrom', '2026-10-01')
        ->json('data.id');

    expect($second)->not->toBe($first);

    $previous = EmployeeProfile::query()->findOrFail($first);

    expect($previous->is_current)->toBeFalse()
        ->and($previous->effective_to?->toDateString())->toBe('2026-10-01');

    $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffA->getKey()}/employee-profile")
        ->assertOk()
        ->assertJsonPath('data.id', $second)
        ->assertJsonPath('data.position', 'Head of Procurement');

    $history = $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles")
        ->assertOk()
        ->json('data');

    expect($history)->toHaveCount(2)
        ->and(collect($history)->pluck('id')->all())->toContain($first, $second);
});

it('does not create a new version when the profile values are unchanged', function () {
    $payload = [
        'card_id' => 'CARD-3003',
        'position' => 'Officer',
    ];

    $first = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", $payload)
        ->assertStatus(201)
        ->json('data.id');

    $second = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", $payload)
        ->assertStatus(201)
        ->json('data.id');

    expect($second)->toBe($first)
        ->and(EmployeeProfile::query()->where('user_id', $this->staffA->getKey())->count())->toBe(1);
});

it('rejects a card id already used by a current profile in the same entity', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-4004',
        ])->assertStatus(201);

    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->peerA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-4004',
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors' => ['card_id']]);
});

it('allows the current profile card id to be reused when versioning', function () {
    $first = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-5005',
            'position' => 'Officer',
        ])
        ->assertStatus(201)
        ->json('data.id');

    $second = $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-5005',
            'position' => 'Senior Officer',
        ])
        ->assertStatus(201)
        ->json('data.id');

    expect($second)->not->toBe($first);
});

it('allows the same card id in another entity', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'card_id' => 'CARD-6006',
        ])->assertStatus(201);

    $this->actingAs($this->adminB, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffB->getKey()}/employee-profiles", [
            'card_id' => 'CARD-6006',
        ])->assertStatus(201);
});

it('rejects organization lookups from another entity', function () {
    $campusB = Campus::factory()->create(['entity_id' => $this->entityB->getKey()]);

    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'campus_id' => $campusB->getKey(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors' => ['campus_id']]);
});

it('blocks a user from writing their own or a peer profile', function () {
    $this->actingAs($this->staffA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffA->getKey()}/employee-profiles", [
            'position' => 'Officer',
        ])->assertStatus(403);

    $this->actingAs($this->staffA, 'sanctum')
        ->postJson("/api/v1/users/{$this->peerA->getKey()}/employee-profiles", [
            'position' => 'Hijacked',
        ])->assertStatus(403);
});

it('lets a user manager write their own profile', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->adminA->getKey()}/employee-profiles", [
            'position' => 'Director',
        ])->assertStatus(201);
});

it('hides users of another entity behind a 404', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffB->getKey()}/employee-profile")
        ->assertStatus(404);

    $this->actingAs($this->adminA, 'sanctum')
        ->postJson("/api/v1/users/{$this->staffB->getKey()}/employee-profiles", [
            'position' => 'Cross Entity',
        ])->assertStatus(404);
});

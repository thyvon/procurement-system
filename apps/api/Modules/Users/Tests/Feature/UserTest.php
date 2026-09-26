<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Approvals\Models\TocaEntry;
use Modules\Auth\Models\RefreshToken;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entityA = Entity::create(['code' => 'ENT-A', 'name' => 'Entity A', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->entityB = Entity::create(['code' => 'ENT-B', 'name' => 'Entity B', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->adminA = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'admin.a@test.local']);
    $this->adminA->assignRole('admin');

    $this->staffA = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'staff.a@test.local']);
    $this->staffA->assignRole('staff');

    $this->staffB = User::factory()->create(['entity_id' => $this->entityB->getKey(), 'email' => 'staff.b@test.local']);
    $this->staffB->assignRole('staff');
});

it('lists only users within the authenticated entity', function () {
    // Entity A admin must see exactly their own entity's users — never Entity B's.
    $visibleIds = $this->actingAs($this->adminA, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->json('data.*.id');

    expect($visibleIds)->toContain($this->adminA->getKey(), $this->staffA->getKey())
        ->not->toContain($this->staffB->getKey());
});

it('prevents an admin from reading a user of another entity', function () {
    // Cross-entity records are invisible: entity scoping is applied while the
    // route model is resolved, so the record is simply not found (404) rather
    // than found and refused (403).
    $this->actingAs($this->adminA, 'sanctum')
        ->getJson("/api/v1/users/{$this->staffB->getKey()}")
        ->assertStatus(404);
});

it('creates a user in the actor entity with the staff role by default', function () {
    $created = $this->actingAs($this->adminA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'New Staff',
            'email' => 'new.staff@test.local',
            'password' => 'long-password-123',
        ])
        ->assertStatus(201);

    expect($created->json('data.entityId'))->toBe($this->entityA->getKey())
        ->and($created->json('data.roles'))->toContain('staff')
        ->and(Str::isUlid((string) $created->json('data.id')))->toBeFalse();

    expect(User::query()->where('email', 'new.staff@test.local')->first()->entity_id)
        ->toBe($this->entityA->getKey());
});

it('forbids non-admins from creating users', function () {
    $this->actingAs($this->staffA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@test.local',
            'password' => 'long-password-123',
        ])->assertStatus(403);

    expect(User::query()->where('email', 'sneaky@test.local')->exists())->toBeFalse();
});

it('updates name and roles of a same-entity user', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", [
            'name' => 'Renamed Staff',
            'roles' => ['admin'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Staff')
        ->assertJsonPath('data.roles.0', 'admin');
});

it('rejects weak passwords and duplicate emails with 422 envelope', function () {
    $response = $this->actingAs($this->adminA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Weak',
            'email' => 'staff.a@test.local',
            'password' => 'short',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);
});

it('rejects role names that do not exist in the roles table', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Unknown Role',
            'email' => 'unknown.role@test.local',
            'password' => 'long-password-123',
            'roles' => ['superuser'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['errors']);

    expect(User::query()->where('email', 'unknown.role@test.local')->exists())->toBeFalse();
});

it('accepts a role created after boot', function () {
    Role::findOrCreate('manager', 'sanctum');

    $created = $this->actingAs($this->adminA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Manager User',
            'email' => 'manager@test.local',
            'password' => 'long-password-123',
            'roles' => ['manager'],
        ])
        ->assertStatus(201);

    expect($created->json('data.roles'))->toContain('manager');
});

it('prevents a non-admin from self-promoting via roles', function () {
    $this->actingAs($this->staffA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", [
            'name' => 'Still Staff',
            'roles' => ['admin'],
        ])
        ->assertOk()
        ->assertJsonPath('data.roles.0', 'staff');

    expect($this->staffA->refresh()->hasRole('admin'))->toBeFalse()
        ->and($this->staffA->hasRole('staff'))->toBeTrue();
});

it('deactivates instead of hard-deleting and revokes all tokens', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'staff.a@test.local',
        'password' => 'password',
    ]);
    $login->assertOk();
    $plainRefresh = $login->json('data.refresh_token');

    $this->actingAs($this->adminA, 'sanctum')
        ->deleteJson("/api/v1/users/{$this->staffA->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.deactivated', true);

    expect($this->staffA->refresh()->is_active)->toBeFalse()
        ->and(PersonalAccessToken::count())->toBe(0)
        ->and(RefreshToken::query()->where('user_id', $this->staffA->getKey())->whereNull('revoked_at')->count())->toBe(0)
        ->and(User::withTrashed()->find($this->staffA->getKey())->deleted_at)->toBeNull();
});

it('syncs the authority entries assigned to a user', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entityA->getKey(),
        'name' => 'Evaluation band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

    $this->actingAs($this->adminA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", ['toca_entry_ids' => [$entry->getKey()]])
        ->assertOk();

    expect(DB::table('toca_entry_user')->where('user_id', $this->staffA->getKey())->pluck('toca_entry_id')->all())
        ->toBe([$entry->getKey()]);

    // Replace-all: sending an empty list clears the assignment.
    $this->actingAs($this->adminA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", ['toca_entry_ids' => []])
        ->assertOk();

    expect(DB::table('toca_entry_user')->where('user_id', $this->staffA->getKey())->exists())->toBeFalse();
});

it('assigns the authority entries passed when creating a user', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entityA->getKey(),
        'name' => 'Evaluation band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

    $created = $this->actingAs($this->adminA, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'New Approver',
            'email' => 'new.approver@test.local',
            'password' => 'long-password-123',
            'toca_entry_ids' => [$entry->getKey()],
        ])
        ->assertStatus(201);

    expect(DB::table('toca_entry_user')->where('user_id', $created->json('data.id'))->pluck('toca_entry_id')->all())
        ->toBe([$entry->getKey()]);
});

it('ignores authority entries when the actor lacks approvals.manage', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entityA->getKey(),
        'name' => 'Evaluation band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

    $this->actingAs($this->staffA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", [
            'name' => 'Still Staff',
            'toca_entry_ids' => [$entry->getKey()],
        ])
        ->assertOk();

    expect(DB::table('toca_entry_user')->where('user_id', $this->staffA->getKey())->exists())->toBeFalse();
});

it('rejects authority entry ids that do not exist or belong to another entity', function () {
    $foreign = TocaEntry::create([
        'entity_id' => $this->entityB->getKey(),
        'name' => 'Foreign band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

    $missing = $this->actingAs($this->adminA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", ['toca_entry_ids' => ['01JXZZZZZZZZZZZZZZZZZZZZZZZZ']])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($missing->json('errors'))->toHaveKey('toca_entry_ids.0');

    $crossEntity = $this->actingAs($this->adminA, 'sanctum')
        ->patchJson("/api/v1/users/{$this->staffA->getKey()}", ['toca_entry_ids' => [$foreign->getKey()]])
        ->assertStatus(422);

    expect($crossEntity->json('errors'))->toHaveKey('toca_entry_ids.0');
});

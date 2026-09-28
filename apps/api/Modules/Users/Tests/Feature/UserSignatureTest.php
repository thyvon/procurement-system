<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');

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

it('lets an admin upload a signature for a user', function () {
    $response = $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/signature", [
            'image' => UploadedFile::fake()->image('signature.png', 64, 64),
        ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'avatar', 'signature', 'entityId', 'isActive']]);

    $path = $this->staffA->refresh()->signature_path;

    expect($path)->toStartWith('signatures/user_'.$this->staffA->getKey().'_')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($response->json('data.signature'))->toBe(Storage::disk('public')->url($path));
});

it('keeps superseded signature files on re-upload so printed snapshots still resolve', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/signature", [
            'image' => UploadedFile::fake()->image('first.png', 64, 64),
        ])->assertOk();

    $firstPath = $this->staffA->refresh()->signature_path;

    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/signature", [
            'image' => UploadedFile::fake()->image('second.png', 64, 64),
        ])->assertOk();

    $secondPath = $this->staffA->refresh()->signature_path;

    expect($secondPath)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($secondPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($firstPath))->toBeTrue();
});

it('rejects non-image and oversized files with the 422 envelope', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/signature", [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/signature", [
            'image' => UploadedFile::fake()->image('huge.jpg', 64, 64)->size(3000),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($this->staffA->refresh()->signature_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('signatures'))->toBeEmpty();
});

it('forbids an admin from changing the signature of a user in another entity', function () {
    // Cross-entity records are invisible (404) — entity scoping applies while
    // the route model is resolved, before any policy check.
    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffB->getKey()}/signature", [
            'image' => UploadedFile::fake()->image('signature.png', 64, 64),
        ])
        ->assertStatus(404);

    expect($this->staffB->refresh()->signature_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('signatures'))->toBeEmpty();
});

it('requires authentication', function () {
    $this->postJson("/api/v1/users/{$this->staffA->getKey()}/signature")
        ->assertStatus(401);
});

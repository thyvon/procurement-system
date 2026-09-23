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

it('lets a user upload their own avatar', function () {
    $response = $this->actingAs($this->staffA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('avatar.jpg', 64, 64),
        ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'avatar', 'entityId', 'isActive']]);

    $path = $this->staffA->refresh()->avatar_path;

    expect($path)->toStartWith('avatars/user_'.$this->staffA->getKey().'_')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($response->json('data.avatar'))->toBe(Storage::disk('public')->url($path));
});

it('replaces the previous avatar file on re-upload', function () {
    $this->actingAs($this->staffA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('first.jpg', 64, 64),
        ])->assertOk();

    $firstPath = $this->staffA->refresh()->avatar_path;

    $this->actingAs($this->staffA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('second.jpg', 64, 64),
        ])->assertOk();

    $secondPath = $this->staffA->refresh()->avatar_path;

    expect($secondPath)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($secondPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($firstPath))->toBeFalse();
});

it('rejects non-image and oversized files with the 422 envelope', function () {
    $this->actingAs($this->staffA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    $this->actingAs($this->staffA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('huge.jpg', 64, 64)->size(3000),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($this->staffA->refresh()->avatar_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('avatars'))->toBeEmpty();
});

it('forbids an admin from changing the avatar of a user in another entity', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffB->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('avatar.jpg', 64, 64),
        ])
        ->assertStatus(403);

    expect($this->staffB->refresh()->avatar_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('avatars'))->toBeEmpty();
});

it('lets an admin change the avatar of a same-entity user', function () {
    $this->actingAs($this->adminA, 'sanctum')
        ->post("/api/v1/users/{$this->staffA->getKey()}/avatar", [
            'image' => UploadedFile::fake()->image('avatar.jpg', 64, 64),
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $this->staffA->getKey());

    expect($this->staffA->refresh()->avatar_path)->not->toBeNull();
});

it('requires authentication', function () {
    $this->postJson("/api/v1/users/{$this->staffA->getKey()}/avatar")
        ->assertStatus(401);
});

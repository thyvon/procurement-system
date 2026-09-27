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

    $this->admin = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'admin.logo@test.local']);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entityA->getKey(), 'email' => 'staff.logo@test.local']);
    $this->staff->assignRole('staff');
});

it('lets an admin upload the entity logo', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->image('logo.png', 64, 64),
        ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'timezone', 'locale', 'logo', 'isActive', 'createdAt']]);

    $path = $this->entityA->refresh()->logo_path;

    expect($path)->toStartWith('logos/entity_'.$this->entityA->getKey().'_')
        ->and($this->entityA->updated_by)->toBe($this->admin->getKey())
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($response->json('data.logo'))->toBe(Storage::disk('public')->url($path));
});

it('replaces the previous logo file on re-upload', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->image('first.png', 64, 64),
        ])->assertOk();

    $firstPath = $this->entityA->refresh()->logo_path;

    $this->actingAs($this->admin, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->image('second.png', 64, 64),
        ])->assertOk();

    $secondPath = $this->entityA->refresh()->logo_path;

    expect($secondPath)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($secondPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($firstPath))->toBeFalse();
});

it('rejects non-image and oversized files with the 422 envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    $this->actingAs($this->admin, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->image('huge.png', 64, 64)->size(3000),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($this->entityA->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBeEmpty();
});

it('forbids staff from uploading the logo', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->post("/api/v1/entities/{$this->entityA->getKey()}/logo", [
            'image' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])
        ->assertStatus(403);

    expect($this->entityA->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBeEmpty();
});

it('exposes the logo as null before one is uploaded', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/entities/{$this->entityA->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.logo', null);
});

it('requires authentication', function () {
    $this->postJson("/api/v1/entities/{$this->entityA->getKey()}/logo")
        ->assertStatus(401);
});

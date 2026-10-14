<?php

use Illuminate\Support\Facades\Storage;
use Modules\Organization\Models\Entity;

beforeEach(function () {
    Storage::fake('public');
});

it('returns the first active entity branding without authentication', function () {
    $first = Entity::create(['code' => 'ENT-1', 'name' => 'First Entity', 'timezone' => 'UTC', 'locale' => 'en', 'logo_path' => 'logos/first.png']);
    Entity::create(['code' => 'ENT-2', 'name' => 'Second Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $response = $this->getJson('/api/v1/branding');

    $response->assertOk()
        ->assertJson(['data' => [
            'name' => 'First Entity',
            'logo' => Storage::disk('public')->url('logos/first.png'),
        ]]);

    expect($response->json('data.name'))->toBe($first->name);
});

it('skips inactive entities', function () {
    Entity::create(['code' => 'ENT-1', 'name' => 'Inactive', 'timezone' => 'UTC', 'locale' => 'en', 'is_active' => false]);
    Entity::create(['code' => 'ENT-2', 'name' => 'Active', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJson(['data' => ['name' => 'Active']]);
});

it('returns nulls when no entity exists', function () {
    $this->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJson(['data' => ['name' => null, 'logo' => null]]);
});

<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-TGC', 'name' => 'Contacts Entity', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');
});

it('creates a username-only contact', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/contacts', [
            'name' => 'Vun Thy',
            'username' => '@VunThyPro',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.username', '@vunthypro')
        ->assertJsonPath('data.phone', null);
});

it('still creates a phone contact', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/contacts', [
            'name' => 'Supplier Co',
            'phone' => '+85511766701',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.phone', '+85511766701')
        ->assertJsonPath('data.username', null);
});

it('rejects a contact with neither phone nor username', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/contacts', ['name' => 'No Target'])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('rejects a malformed username', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/contacts', [
            'name' => 'Bad Handle',
            'phone' => '+85512345678',
            'username' => 'missingatsign',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

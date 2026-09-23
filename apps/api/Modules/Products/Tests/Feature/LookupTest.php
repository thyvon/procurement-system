<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Modules\Organization\Models\Entity;
use Modules\Products\Models\Brand;
use Modules\Products\Models\ProductCategory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-P', 'name' => 'Entity P', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-Q', 'name' => 'Entity Q', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

it('creates a category as admin and returns it localized-ready', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/categories', [
            'name' => 'Office Supplies',
            'name_km' => 'សម្ភារៈការិយាល័យ',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.nameKm', 'សម្ភារៈការិយាល័យ')
        ->assertJsonPath('data.shortCode', 'OFFICE SUP');

    expect($response->json('data.code'))->toMatch('/^CAT-\d{2}-\d{3}$/');
});

it('forbids staff from creating categories', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/products/categories', ['code' => 'X1', 'name' => 'Nope'])
        ->assertStatus(403);
});

it('returns the category tree with children nested', function () {
    $root = ProductCategory::create([
        'code' => 'ROOT', 'name' => 'Root', 'entity_id' => $this->entity->getKey(),
    ]);
    ProductCategory::create([
        'code' => 'CHILD', 'name' => 'Child', 'entity_id' => $this->entity->getKey(),
        'parent_id' => $root->getKey(), 'sort_order' => 1,
    ]);

    $tree = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/categories/tree')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->json('data.0');

    expect($tree['children'])->toHaveCount(1)
        ->and($tree['children'][0]['code'])->toBe('CHILD');
});

it('keeps an explicitly provided short_code', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/categories', ['name' => 'Beverages', 'short_code' => 'BEV'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortCode', 'BEV');
});

it('does not reuse codes from soft-deleted categories within the same entity', function () {
    ProductCategory::create([
        'code' => 'CAT-26-001',
        'name' => 'Deleted Category',
        'entity_id' => $this->entity->getKey(),
    ])->delete();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/categories', ['name' => 'Replacement'])
        ->assertStatus(201)
        ->assertJsonPath('data.code', 'CAT-26-002');
});

it('manages brands through the generic lookup flow', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/brands', ['name' => 'Acme'])
        ->assertStatus(201);

    $brandId = $created->json('data.id');

    $this->patchJson("/api/v1/products/brands/{$brandId}", ['description' => 'Updated'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated');

    expect(Brand::count())->toBe(1);
});

it('creates uom with sub units and conversion factors', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/uoms', [
            'name' => 'Box',
            'short_name' => 'BOX',
            'sub_units' => [
                ['name' => 'Piece', 'short_name' => 'PCS', 'conversion_factor' => 24],
            ],
        ])
        ->assertStatus(201);

    expect($response->json('data.subUnits'))->toHaveCount(1)
        ->and($response->json('data.subUnits.0.conversionFactor'))->toEqual(24.0);
});

it('defaults uom short_name from the name when omitted', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/uoms', ['name' => 'Crate'])
        ->assertStatus(201)
        ->assertJsonPath('data.shortName', 'Crate');
});

it('isolates product lookups per entity', function () {
    Brand::create(['name' => 'ForeignBrand', 'entity_id' => $this->otherEntity->getKey()]);

    $names = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/products/brands')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->not->toContain('ForeignBrand');
});

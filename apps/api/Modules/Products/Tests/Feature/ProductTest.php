<?php

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Organization\Models\Entity;
use Modules\Products\Models\Product;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-PR', 'name' => 'Entity PR', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-PS', 'name' => 'Entity PS', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function createProduct(Entity $entity, array $overrides = []): Product
{
    return Product::create([
        ...[
            'code' => 'P-'.Str::random(6),
            'name' => 'Test Product',
            'entity_id' => $entity->getKey(),
            'purchase_price' => 12.5,
        ],
        ...$overrides,
    ]);
}

it('creates a product with khmer name as admin', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'code' => 'STF-900',
            'name' => 'A4 Copy Paper',
            'name_km' => 'ក្រដាស់តពុន A4',
            'purchase_price' => 3.75,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.nameKm', 'ក្រដាស់តពុន A4')
        ->assertJsonPath('data.purchasePrice', 3.75);
});

it('forbids staff from creating products', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/products/items', ['code' => 'X1', 'name' => 'Nope'])
        ->assertStatus(403);
});

it('generates a code on the server when omitted', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', ['name' => 'No Code Product'])
        ->assertStatus(201);

    expect($response->json('data.code'))->toMatch('/^PRD-\d{2}-\d{3}$/');
});

it('rejects duplicate codes with a 422 envelope', function () {
    createProduct($this->entity, ['code' => 'DUP-1']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'code' => 'DUP-1',
            'name' => 'Duplicate',
        ])->assertStatus(422)->assertJsonPath('statusCode', 422);
});

it('filters the list by search query via meilisearch', function () {
    createProduct($this->entity, ['code' => 'SRCH-1', 'name' => 'Wireless Mouse']);
    createProduct($this->entity, ['code' => 'SRCH-2', 'name' => 'USB Flash Drive']);

    // Give the Scout engine a beat to index.
    sleep(2);

    $codes = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?search=mouse')
        ->assertOk()
        ->json('data.*.code');

    expect($codes)->toContain('SRCH-1')->not->toContain('SRCH-2');
});

it('never returns other entities products even when searching their names', function () {
    createProduct($this->otherEntity, ['code' => 'FOR-1', 'name' => 'Foreign Unique Widget']);

    sleep(2);

    $codes = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?search=Foreign Unique Widget')
        ->assertOk()
        ->json('data.*.code');

    expect($codes)->not->toContain('FOR-1');
});

it('updates price and fields', function () {
    $product = createProduct($this->entity);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/products/items/{$product->getKey()}", [
            'purchase_price' => 99.9,
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('data.purchasePrice', 99.9);
});

it('soft-deletes a product', function () {
    $product = createProduct($this->entity);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/products/items/{$product->getKey()}")
        ->assertOk();

    expect($product->refresh()->deleted_at)->not->toBeNull();
});

it('returns dropdown refs in one call', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/refs?with_products=1')
        ->assertOk()
        ->assertJsonStructure(['data' => ['categories', 'groups', 'brands', 'uoms', 'products']]);
});

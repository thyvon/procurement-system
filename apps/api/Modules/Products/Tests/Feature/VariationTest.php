<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Organization\Models\Entity;
use Modules\Products\Models\Product;
use Modules\Products\Models\VariationTemplate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-V', 'name' => 'Entity V', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');
});

function makeProduct(Entity $entity, array $overrides = []): Product
{
    return Product::create([
        ...[
            'code' => 'V-'.Str::random(6),
            'name' => 'Generic',
            'product_type' => 'single',
            'entity_id' => $entity->getKey(),
        ],
        ...$overrides,
    ]);
}

it('creates a variation template with options', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/variation-templates', [
            'name' => 'Size',
            'options' => [
                ['value' => 'Small'],
                ['value' => 'Large'],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonCount(2, 'data.options')
        ->assertJsonPath('data.options.0.value', 'Small');
});

it('lists variation templates', function () {
    VariationTemplate::create([
        'name' => 'Color', 'entity_id' => $this->entity->getKey(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/products/variation-templates')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Color');
});

it('rejects duplicate template names within entity with 422', function () {
    VariationTemplate::create([
        'name' => 'Color', 'entity_id' => $this->entity->getKey(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/variation-templates', ['name' => 'Color'])
        ->assertStatus(422);
});

it('merges single products into a variable family with variants', function () {
    $parent = makeProduct($this->entity, ['code' => 'V-PARENT', 'name' => 'T-Shirt']);
    $small = makeProduct($this->entity, ['code' => 'V-SMALL', 'name' => 'T-Shirt S', 'purchase_price' => 5]);
    $large = makeProduct($this->entity, ['code' => 'V-LARGE', 'name' => 'T-Shirt L', 'purchase_price' => 7]);

    $sizeId = VariationTemplate::create([
        'name' => 'Size', 'entity_id' => $this->entity->getKey(),
    ])->getKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/merge-variation', [
            'parentId' => $parent->getKey(),
            'templateIds' => [$sizeId],
            'assignments' => [
                ['productId' => $parent->getKey(), 'values' => ['Size' => 'Medium']],
                ['productId' => $small->getKey(), 'values' => ['Size' => 'Small']],
                ['productId' => $large->getKey(), 'values' => ['Size' => 'Large']],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.productType', 'variable');

    expect($parent->refresh()->variants()->count())->toBe(3)
        ->and(Product::withTrashed()->find($small->getKey())->deleted_at)->not->toBeNull();

    $variant = $parent->variants()->where('code', 'V-SMALL')->first();
    expect($variant->option_values)->toBe(['Size' => 'Small'])
        ->and((float) $variant->purchase_price)->toBe(5.0);
});

it('rejects merge when parent is not assigned', function () {
    $parent = makeProduct($this->entity, ['code' => 'V-P2']);
    $child = makeProduct($this->entity, ['code' => 'V-C2']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/merge-variation', [
            'parentId' => $parent->getKey(),
            'templateIds' => [Str::ulid()->toString()],
            'assignments' => [
                ['productId' => $child->getKey(), 'values' => ['Size' => 'S']],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'InvalidMergeRequest');

    expect($parent->refresh()->product_type)->toBe('single')
        ->and($child->refresh()->deleted_at)->toBeNull();
});

it('rejects merging a product that already has variants', function () {
    $parent = makeProduct($this->entity, ['code' => 'V-P3', 'product_type' => 'variable']);
    $variable = makeProduct($this->entity, ['code' => 'V-VAR', 'product_type' => 'variable']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/merge-variation', [
            'parentId' => $parent->getKey(),
            'templateIds' => [Str::ulid()->toString()],
            'assignments' => [
                ['productId' => $parent->getKey(), 'values' => ['A' => 'x']],
                ['productId' => $variable->getKey(), 'values' => ['A' => 'y']],
            ],
        ])
        ->assertStatus(422);

    expect(Product::find($variable->getKey()))->not->toBeNull();
});

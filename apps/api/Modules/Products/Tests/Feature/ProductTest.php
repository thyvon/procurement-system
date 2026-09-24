<?php

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

it('returns pagination meta on the list index', function () {
    createProduct($this->entity, ['code' => 'META-1', 'name' => 'Meta One']);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.code'))->toBe('META-1');
});

it('pages the list with page and per_page', function () {
    foreach (range(1, 5) as $i) {
        createProduct($this->entity, [
            'code' => 'PAGE-'.$i,
            'name' => 'Paged Product '.$i,
        ]);
    }

    $page1 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?per_page=2&page=1')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonPath('meta.total', 5)
        ->json('data.*.code');

    $page2 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->json('data.*.code');

    expect($page1)->toHaveCount(2)
        ->and($page2)->toHaveCount(2)
        ->and(array_intersect($page1, $page2))->toBe([]);
});

it('filters the list by status', function () {
    createProduct($this->entity, ['code' => 'ST-ACT', 'name' => 'Active Product', 'is_active' => true]);
    createProduct($this->entity, ['code' => 'ST-INA', 'name' => 'Inactive Product', 'is_active' => false]);

    $active = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?status=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $inactive = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/products/items?status=inactive')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    expect($active)->toContain('ST-ACT')->not->toContain('ST-INA')
        ->and($inactive)->toContain('ST-INA')->not->toContain('ST-ACT');
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

function makeSizeTemplate(Entity $entity): VariationTemplate
{
    $template = VariationTemplate::create([
        'name' => 'Size',
        'entity_id' => $entity->getKey(),
    ]);
    $template->options()->create(['value' => 'Small', 'sort_order' => 0]);
    $template->options()->create(['value' => 'Large', 'sort_order' => 1]);

    return $template;
}

it('shows a product with variants and template ids', function () {
    $template = makeSizeTemplate($this->entity);
    $smallOption = $template->options()->where('value', 'Small')->first();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'code' => 'VAR-1',
            'name' => 'T-Shirt',
            'product_type' => 'variable',
            'template_ids' => [$template->getKey()],
            'variants' => [
                [
                    'code' => 'TS-S',
                    'name' => 'T-Shirt Small',
                    'option_values' => [$template->getKey() => $smallOption->getKey()],
                    'purchase_price' => 5,
                ],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.productType', 'variable')
        ->assertJsonPath('data.templateIds.0', $template->getKey())
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.optionValues.'.$template->getKey(), $smallOption->getKey());

    $productId = $response->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/products/items/{$productId}")
        ->assertOk()
        ->assertJsonPath('data.templateIds.0', $template->getKey())
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.code', 'TS-S')
        ->assertJsonPath('data.variants.0.purchasePrice', 5);
});

it('creates a variable product with a full variant matrix', function () {
    $template = makeSizeTemplate($this->entity);
    $small = $template->options()->where('value', 'Small')->first();
    $large = $template->options()->where('value', 'Large')->first();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'Hoodie',
            'product_type' => 'variable',
            'template_ids' => [$template->getKey()],
            'variants' => [
                [
                    'option_values' => [$template->getKey() => $small->getKey()],
                    'purchase_price' => 10,
                ],
                [
                    'code' => 'HD-L',
                    'name' => 'Hoodie Large',
                    'option_values' => [$template->getKey() => $large->getKey()],
                    'purchase_price' => 12,
                    'sub_unit_id' => null,
                    'sub_unit_purchase_price' => null,
                ],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonCount(2, 'data.variants');

    $product = Product::query()->find($response->json('data.id'));

    expect($product->variants()->count())->toBe(2)
        ->and($product->variationTemplates()->pluck('id')->all())->toBe([$template->getKey()]);

    // Blank name/code are filled server-side from labels / left null.
    $auto = $product->variants()->whereNull('code')->first();
    expect($auto->name)->toBe('Hoodie — Small');
});

it('updates a variable product by syncing the variant matrix', function () {
    $template = makeSizeTemplate($this->entity);
    $small = $template->options()->where('value', 'Small')->first();
    $large = $template->options()->where('value', 'Large')->first();

    $product = createProduct($this->entity, [
        'code' => 'SYNC-1',
        'name' => 'Sneakers',
        'product_type' => 'variable',
    ]);
    $product->variationTemplates()->attach($template->getKey());

    $kept = $product->variants()->create([
        'entity_id' => $this->entity->getKey(),
        'code' => 'SN-S',
        'name' => 'Sneakers Small',
        'option_values' => [$template->getKey() => $small->getKey()],
        'purchase_price' => 20,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/products/items/{$product->getKey()}", [
            'product_type' => 'variable',
            'template_ids' => [$template->getKey()],
            'variants' => [
                [
                    'id' => $kept->getKey(),
                    'code' => 'SN-S',
                    'name' => 'Sneakers Small',
                    'option_values' => [$template->getKey() => $small->getKey()],
                    'purchase_price' => 21,
                ],
                [
                    'code' => 'SN-L',
                    'name' => 'Sneakers Large',
                    'option_values' => [$template->getKey() => $large->getKey()],
                    'purchase_price' => 22,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.variants');

    expect($product->refresh()->variants()->count())->toBe(2)
        ->and($product->variants()->find($kept->getKey())->purchase_price)->toBe('21.0000');
});

it('clears variants when a variable product is switched to single', function () {
    $template = makeSizeTemplate($this->entity);
    $small = $template->options()->where('value', 'Small')->first();

    $product = createProduct($this->entity, [
        'code' => 'SW-1',
        'name' => 'Cap',
        'product_type' => 'variable',
    ]);
    $product->variationTemplates()->attach($template->getKey());
    $product->variants()->create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Cap Small',
        'option_values' => [$template->getKey() => $small->getKey()],
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/products/items/{$product->getKey()}", [
            'product_type' => 'single',
        ])
        ->assertOk()
        ->assertJsonPath('data.productType', 'single')
        ->assertJsonCount(0, 'data.variants');

    expect($product->refresh()->variants()->count())->toBe(0)
        ->and($product->variationTemplates()->count())->toBe(0);
});

it('rejects a variable product without variants', function () {
    $template = makeSizeTemplate($this->entity);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'No Matrix',
            'product_type' => 'variable',
            'template_ids' => [$template->getKey()],
            'variants' => [],
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('rejects duplicate variant combinations', function () {
    $template = makeSizeTemplate($this->entity);
    $small = $template->options()->where('value', 'Small')->first();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'Dup Combo',
            'product_type' => 'variable',
            'template_ids' => [$template->getKey()],
            'variants' => [
                ['option_values' => [$template->getKey() => $small->getKey()]],
                ['option_values' => [$template->getKey() => $small->getKey()]],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

it('rejects sub-unit purchase price above base price', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'Too Expensive Sub',
            'purchase_price' => 5,
            'sub_unit_purchase_price' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('rejects incomplete variant combos for every selected template', function () {
    $size = makeSizeTemplate($this->entity);
    $color = VariationTemplate::create([
        'name' => 'Color',
        'entity_id' => $this->entity->getKey(),
    ]);
    $color->options()->create(['value' => 'Red', 'sort_order' => 0]);
    $red = $color->options()->where('value', 'Red')->first();
    $small = $size->options()->where('value', 'Small')->first();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'Partial Combo',
            'product_type' => 'variable',
            'template_ids' => [$size->getKey(), $color->getKey()],
            'variants' => [
                [
                    // Missing Color.
                    'option_values' => [$size->getKey() => $small->getKey()],
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    // Full combo still accepted with the same payload shape.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/items', [
            'name' => 'Full Combo',
            'product_type' => 'variable',
            'template_ids' => [$size->getKey(), $color->getKey()],
            'variants' => [
                [
                    'option_values' => [
                        $size->getKey() => $small->getKey(),
                        $color->getKey() => $red->getKey(),
                    ],
                ],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonCount(1, 'data.variants');
});

it('leaves the variant matrix untouched on partial updates without variation keys', function () {
    $template = makeSizeTemplate($this->entity);
    $small = $template->options()->where('value', 'Small')->first();

    $product = createProduct($this->entity, [
        'code' => 'PART-1',
        'name' => 'Partial',
        'product_type' => 'variable',
    ]);
    $product->variationTemplates()->attach($template->getKey());
    $product->variants()->create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Partial Small',
        'option_values' => [$template->getKey() => $small->getKey()],
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/products/items/{$product->getKey()}", [
            'description' => 'Only description changes',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.variants');

    expect($product->refresh()->variants()->count())->toBe(1)
        ->and($product->description)->toBe('Only description changes');
});

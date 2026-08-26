<?php

use App\Models\User;
use Modules\Organization\Models\Entity;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-IMP', 'name' => 'Entity IMP', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    ProductCategory::create([
        'code' => 'OFF', 'name' => 'Office', 'entity_id' => $this->entity->getKey(),
    ]);
});

it('downloads a csv template with headers and examples', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/products/import/template')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('code,name,name_km,category_code')
        ->and($response->getContent())->toContain('STF-001');
});

it('imports valid rows and reports per-row errors for bad ones', function () {
    $rows = [
        ['code' => 'IMP-1', 'name' => 'Paper', 'name_km' => 'ក្រដាស់', 'category_code' => 'OFF', 'purchase_price' => '3.5'],
        ['code' => '', 'name' => 'Missing code'],
        ['code' => 'IMP-1', 'name' => 'Duplicate in file'],
        ['code' => 'IMP-3', 'name' => 'Bad category', 'category_code' => 'NOPE'],
    ];

    $result = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/import', ['rows' => $rows])
        ->assertOk()
        ->json('data');

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(3)
        ->and(count($result['errors']))->toBe(3);

    expect(Product::query()->where('code', 'IMP-1')->exists())->toBeTrue()
        ->and(Product::query()->where('name', 'Duplicate in file')->exists())->toBeFalse();

    $imported = Product::query()->where('code', 'IMP-1')->first();
    expect($imported->name_km)->toBe('ក្រដាស់')
        ->and((float) $imported->purchase_price)->toBe(3.5)
        ->and($imported->product_category_id)->not->toBeNull();
});

it('rejects an empty payload with 422', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/products/import', ['rows' => []])
        ->assertStatus(422);
});

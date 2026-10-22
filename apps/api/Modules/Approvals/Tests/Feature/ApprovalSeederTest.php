<?php

use App\Models\User;
use Modules\Approvals\Database\Seeders\ApprovalsDatabaseSeeder;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Organization\Models\Entity;
use Modules\Payments\Database\Seeders\PaymentsDatabaseSeeder;

beforeEach(function () {
    $this->entity = Entity::create(['code' => 'ENT-SD', 'name' => 'Entity SD', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'email' => 'admin@procurement.local',
    ]);
    $this->admin->assignRole('admin');
});

it('seeds one active open band per payment setting so flow saves stay possible', function () {
    $this->seed(PaymentsDatabaseSeeder::class);
    $this->seed(ApprovalsDatabaseSeeder::class);

    foreach (['petty_cash_request', 'advance_request', 'cash_request', 'cash_clearance', 'advance_liquidation'] as $subjectType) {
        $setting = ApprovalSetting::query()->where('subject_type', $subjectType)->firstOrFail();
        $flows = ApprovalFlow::query()->where('approval_setting_id', $setting->getKey())->get();

        expect($flows)->toHaveCount(1);
        expect($flows[0]->is_active)->toBeTrue();
        expect((float) $flows[0]->min_amount)->toBe(0.0);
        expect($flows[0]->max_amount)->toBeNull();
        expect(ApprovalStep::query()->where('approval_flow_id', $flows[0]->getKey())->whereNull('label_kh')->count())->toBe(0);
    }

    // The regression this guards: saving a seeded flow must not hit
    // "An open-ended band (no maximum) cannot be followed by another active band."
    $flow = ApprovalFlow::query()->where('code', 'advance-standard')->firstOrFail();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/approvals/flows/{$flow->getKey()}", ['name' => 'Standard renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Standard renamed');
});

it('seeds the evaluation workflow as two non-overlapping active bands', function () {
    $this->seed(ApprovalsDatabaseSeeder::class);

    $setting = ApprovalSetting::query()->where('subject_type', 'evaluation')->firstOrFail();
    $bands = ApprovalFlow::query()
        ->where('approval_setting_id', $setting->getKey())
        ->orderBy('min_amount')
        ->get();

    expect($bands)->toHaveCount(2);
    expect((float) $bands[0]->min_amount)->toBe(0.0);
    expect((float) $bands[0]->max_amount)->toBe(1000.0);
    expect((float) $bands[1]->min_amount)->toBe(1001.0);
    expect($bands[1]->max_amount)->toBeNull();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/approvals/flows/{$bands[1]->getKey()}", ['name' => 'High value renamed'])
        ->assertOk();
});

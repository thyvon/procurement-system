<?php

use App\Models\User;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;

beforeEach(function () {
    $this->entity = Entity::create(['code' => 'ENT-TC', 'name' => 'Entity TC', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->viewer = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->viewer->assignRole('staff');

    $this->approver = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->approver->assignRole('staff');
});

function tocaPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Senior approver',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ], $overrides);
}

function tocaEntryUrl(TocaEntry $entry): string
{
    return "/api/v1/approvals/toca-entries/{$entry->getKey()}";
}

function tocaFlowStepKey(): string
{
    $setting = ApprovalSetting::create([
        'entity_id' => test()->entity->getKey(),
        'subject_type' => 'evaluation',
        'name' => 'Evaluation Approval',
        'is_active' => true,
    ]);

    $flow = ApprovalFlow::create([
        'entity_id' => test()->entity->getKey(),
        'approval_setting_id' => $setting->getKey(),
        'code' => 'evaluation-test',
        'name' => 'Test flow',
        'min_amount' => 0,
        'max_amount' => null,
        'is_active' => true,
    ]);

    foreach (['checked', 'approved'] as $index => $key) {
        ApprovalStep::create([
            'entity_id' => test()->entity->getKey(),
            'approval_flow_id' => $flow->getKey(),
            'position' => $index + 1,
            'key' => $key,
            'label' => $key === 'checked' ? 'Checked By' : 'Approved By',
            'action_mode' => ApprovalStep::MODE_DECIDE,
            'allowed_actions' => ['approve', 'reject', 'return'],
        ]);
    }

    return 'checked';
}

it('requires authentication', function () {
    $this->getJson('/api/v1/approvals/toca-entries')->assertStatus(401);
});

it('lists authority entries with their users for a viewer', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Senior approver',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);
    $entry->users()->attach($this->approver);

    $response = $this->actingAs($this->viewer, 'sanctum')
        ->getJson('/api/v1/approvals/toca-entries')
        ->assertOk();

    $row = $response->json('data.0');

    expect($row)->toHaveKeys(['id', 'name', 'subjectType', 'stepKey', 'minAmount', 'maxAmount', 'users']);
    expect($row['name'])->toBe('Senior approver')
        ->and($row['users'])->toBe([
            ['id' => $this->approver->getKey(), 'name' => $this->approver->name],
        ]);
});

it('forbids staff from writing authority entries', function () {
    $this->actingAs($this->viewer, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload())
        ->assertStatus(403);
});

it('creates an authority entry', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload())
        ->assertStatus(201);

    $row = $response->json('data');

    expect($row['name'])->toBe('Senior approver')
        ->and($row['users'])->toBe([])
        ->and($row['maxAmount'])->toBe('1000.00');

    $stored = TocaEntry::query()->find($row['id']);
    expect((int) $stored->created_by)->toBe((int) $this->admin->getKey());
    expect((int) $stored->updated_by)->toBe((int) $this->admin->getKey());
});

it('requires a name', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['name' => null]))
        ->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('name');
});

it('rejects an unknown subject type, a duplicate name and an inverted band', function () {
    $unknownType = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['subject_type' => 'contract']))
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($unknownType->json('errors'))->toHaveKey('subject_type');

    TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Senior approver',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

    $duplicateName = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload())
        ->assertStatus(422);

    expect($duplicateName->json('errors'))->toHaveKey('name');

    $inverted = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['min_amount' => 500, 'max_amount' => 100]))
        ->assertStatus(422);

    expect($inverted->json('errors'))->toHaveKey('max_amount');
});

it('updates an authority entry', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Mid band',
        'subject_type' => 'evaluation',
        'min_amount' => 500,
        'max_amount' => 1000,
    ]);

    // Only the maximum changes — the stored minimum still applies.
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(tocaEntryUrl($entry), ['max_amount' => 2000])
        ->assertOk()
        ->assertJsonPath('data.minAmount', '500.00')
        ->assertJsonPath('data.maxAmount', '2000.00');

    // Only the maximum changes and dips below the stored minimum.
    $inverted = $this->actingAs($this->admin, 'sanctum')
        ->putJson(tocaEntryUrl($entry), ['max_amount' => 100])
        ->assertStatus(422);

    expect($inverted->json('errors'))->toHaveKey('max_amount');
});

it('creates an authority entry scoped to a step', function () {
    $key = tocaFlowStepKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['step_key' => $key]))
        ->assertStatus(201)
        ->assertJsonPath('data.stepKey', $key);

    expect(TocaEntry::query()->find($response->json('data.id'))->step_key)->toBe($key);
});

it('rejects a step key that no flow of the subject defines', function () {
    tocaFlowStepKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['step_key' => 'typo']))
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('step_key')
        ->and(TocaEntry::query()->count())->toBe(0);
});

it('scopes and clears the step key on update', function () {
    tocaFlowStepKey();

    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Step band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(tocaEntryUrl($entry), ['step_key' => 'checked'])
        ->assertOk()
        ->assertJsonPath('data.stepKey', 'checked');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(tocaEntryUrl($entry), ['step_key' => null])
        ->assertOk()
        ->assertJsonPath('data.stepKey', null);
});

it('deletes an authority entry', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Doomed band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);
    $entry->users()->attach($this->approver);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(tocaEntryUrl($entry))
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(TocaEntry::query()->find($entry->getKey()))->toBeNull();

    $this->actingAs($this->viewer, 'sanctum')
        ->getJson('/api/v1/approvals/toca-entries')
        ->assertOk()
        ->assertJsonPath('data', []);
});

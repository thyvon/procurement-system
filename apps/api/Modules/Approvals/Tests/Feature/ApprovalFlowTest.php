<?php

use App\Models\User;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Organization\Models\Entity;

beforeEach(function () {
    $this->entity = Entity::create(['code' => 'ENT-FL', 'name' => 'Entity FL', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->viewer = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->viewer->assignRole('staff');

    $this->setting = ApprovalSetting::create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'evaluation',
        'name' => 'Evaluation Approval',
        'is_active' => true,
    ]);

    $this->flow = ApprovalFlow::create([
        'entity_id' => $this->entity->getKey(),
        'approval_setting_id' => $this->setting->getKey(),
        'code' => 'test-flow',
        'name' => 'Test flow',
        'min_amount' => 0,
        'max_amount' => null,
        'is_active' => true,
    ]);

    foreach ([
        [1, 'prepared', 'Prepared By', ApprovalStep::MODE_RECORD, null],
        [2, 'approved', 'Approved By', ApprovalStep::MODE_DECIDE, ['approve', 'reject', 'return']],
    ] as [$position, $key, $label, $mode, $actions]) {
        ApprovalStep::create([
            'entity_id' => $this->entity->getKey(),
            'approval_flow_id' => $this->flow->getKey(),
            'position' => $position,
            'key' => $key,
            'label' => $label,
            'action_mode' => $mode,
            'allowed_actions' => $actions,
        ]);
    }
});

function approvalFlowPayload(array $overrides = []): array
{
    return array_merge([
        'subject_type' => 'evaluation',
        'name' => 'High Value',
        'min_amount' => 1001,
        'max_amount' => null,
        'is_active' => false,
        'steps' => [
            ['key' => 'prepared', 'label' => 'Prepared By', 'action_mode' => 'record'],
            ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => ['approve', 'reject', 'return']],
        ],
    ], $overrides);
}

function approvalFlowUrl(ApprovalFlow $flow): string
{
    return "/api/v1/approvals/flows/{$flow->getKey()}";
}

it('requires authentication', function () {
    $this->getJson('/api/v1/approvals/flows')->assertStatus(401);
    $this->getJson('/api/v1/approvals/settings')->assertStatus(401);
});

it('lists the settings vocabulary for a viewer', function () {
    $this->actingAs($this->viewer, 'sanctum')
        ->getJson('/api/v1/approvals/settings')
        ->assertOk()
        ->assertJsonPath('data.0.subjectType', 'evaluation')
        ->assertJsonPath('data.0.name', 'Evaluation Approval');
});

it('lists flows with their settings and ordered steps', function () {
    $response = $this->actingAs($this->viewer, 'sanctum')
        ->getJson('/api/v1/approvals/flows')
        ->assertOk();

    $flow = $response->json('data.0');

    expect($flow)->toHaveKeys(['id', 'code', 'name', 'minAmount', 'maxAmount', 'isActive', 'setting', 'steps']);
    expect($flow['code'])->toBe('test-flow');
    expect($flow['setting']['subjectType'])->toBe('evaluation');
    expect($flow['steps'])->toHaveCount(2);
    expect($flow['steps'][0])->toMatchArray(['position' => 1, 'key' => 'prepared', 'actionMode' => 'record']);
    expect($flow['steps'][0]['allowedActions'])->toBeNull();
    expect($flow['steps'][1]['allowedActions'])->toBe(['approve', 'reject', 'return']);
});

it('forbids staff from writing the configuration', function () {
    $this->actingAs($this->viewer, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload())
        ->assertStatus(403);

    $this->actingAs($this->viewer, 'sanctum')
        ->putJson(approvalFlowUrl($this->flow), ['name' => 'Renamed'])
        ->assertStatus(403);

    $this->actingAs($this->viewer, 'sanctum')
        ->deleteJson(approvalFlowUrl($this->flow))
        ->assertStatus(403);
});

it('creates a flow with a server-generated code and normalized steps', function () {
    // Shrink the open band so an active band can sit beside it.
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($this->flow), ['max_amount' => 1000])
        ->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload(['is_active' => true, 'min_amount' => 1001]))
        ->assertStatus(201);

    $created = $response->json('data');

    expect($created['code'])->toBe('high-value');
    expect($created['steps'])->toHaveCount(2);
    expect($created['steps'][1]['allowedActions'])->toBe(['approve', 'reject', 'return']);

    $stored = ApprovalFlow::query()->find($created['id']);
    expect((int) $stored->created_by)->toBe((int) $this->admin->getKey());
    expect((int) $stored->updated_by)->toBe((int) $this->admin->getKey());

    // Same name again → unique suffixed code, still server-generated.
    $again = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload())
        ->assertStatus(201);

    expect($again->json('data.code'))->toBe('high-value-2');
});

it('rejects overlapping or unanchored amount bands', function () {
    // Shrink the open band so new active bands can sit beside it.
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($this->flow), ['max_amount' => 1000])
        ->assertOk();

    // Overlaps the 0–1000 band.
    $overlap = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'min_amount' => 500,
            'max_amount' => 800,
            'is_active' => true,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($overlap->json('errors'))->toHaveKey('min_amount');

    // Sits cleanly above 1000.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'min_amount' => 1001,
            'max_amount' => null,
            'is_active' => true,
        ]))
        ->assertStatus(201);

    // Editing the seeded-style second band stays possible (whole-dollar boundary).
    $second = ApprovalFlow::query()->where('min_amount', 1001)->firstOrFail();
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($second), ['name' => 'High value renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'High value renamed');

    // Shut down top-down, then a band that does not anchor at 0.
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($second), ['is_active' => false])
        ->assertOk();
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($this->flow), ['is_active' => false])
        ->assertOk();

    $unanchored = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'min_amount' => 500,
            'max_amount' => null,
            'is_active' => true,
        ]))
        ->assertStatus(422);

    expect($unanchored->json('errors.min_amount.0'))->toContain('start at 0.00');
});

it('validates step definitions', function () {
    $noDecision = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'prepared', 'label' => 'Prepared By', 'action_mode' => 'record'],
            ],
        ]))
        ->assertStatus(422);

    expect($noDecision->json('errors'))->toHaveKey('steps');

    $duplicateKeys = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'reviewed', 'label' => 'Reviewed By', 'action_mode' => 'decide', 'allowed_actions' => ['approve']],
                ['key' => 'reviewed', 'label' => 'Checked By', 'action_mode' => 'decide', 'allowed_actions' => ['approve']],
            ],
        ]))
        ->assertStatus(422);

    expect($duplicateKeys->json('errors'))->toHaveKey('steps');

    $emptyActions = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => []],
            ],
        ]))
        ->assertStatus(422);

    expect($emptyActions->json('errors'))->toHaveKey('steps');

    // Record steps never store actions, whatever the payload says.
    $recordWithActions = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'prepared', 'label' => 'Prepared By', 'action_mode' => 'record', 'allowed_actions' => ['approve']],
                ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => ['approve', 'return']],
            ],
        ]))
        ->assertStatus(201);

    expect($recordWithActions->json('data.steps.0.allowedActions'))->toBeNull();
    expect($recordWithActions->json('data.steps.1.allowedActions'))->toBe(['approve', 'return']);
});

it('stores the show-on-print flag per step, defaulting to visible', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'prepared', 'label' => 'Prepared By', 'action_mode' => 'record', 'show_on_print' => false],
                ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => ['approve']],
            ],
        ]))
        ->assertStatus(201);

    expect($created->json('data.steps.0.showOnPrint'))->toBeFalse();
    expect($created->json('data.steps.1.showOnPrint'))->toBeTrue();

    $invalid = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/flows', approvalFlowPayload([
            'steps' => [
                ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => ['approve'], 'show_on_print' => 'yes'],
            ],
        ]))
        ->assertStatus(422);

    expect($invalid->json('errors'))->toHaveKey('steps.0.show_on_print');
});

it('updates a flow and replaces its steps wholesale', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson(approvalFlowUrl($this->flow), [
            'name' => 'Renamed flow',
            'steps' => [
                ['key' => 'checked', 'label' => 'Checked By', 'action_mode' => 'decide', 'allowed_actions' => ['approve', 'reject']],
                ['key' => 'approved', 'label' => 'Approved By', 'action_mode' => 'decide', 'allowed_actions' => ['approve']],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed flow')
        ->assertJsonPath('data.code', 'test-flow');

    $steps = $response->json('data.steps');

    expect($steps)->toHaveCount(2);
    expect($steps[0])->toMatchArray(['position' => 1, 'key' => 'checked', 'actionMode' => 'decide']);
    expect($steps[1])->toMatchArray(['position' => 2, 'key' => 'approved']);

    expect(
        ApprovalStep::query()->where('approval_flow_id', $this->flow->getKey())->count()
    )->toBe(2);
});

it('blocks deleting a flow while a pending request uses it', function () {
    $request = ApprovalRequest::create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'evaluation',
        'subject_id' => '01J000000000000000000000',
        'approval_setting_id' => $this->setting->getKey(),
        'approval_flow_id' => $this->flow->getKey(),
        'amount_snapshot' => 100,
        'status' => ApprovalRequest::STATUS_PENDING,
        'snapshot' => [],
        'created_by' => $this->admin->getKey(),
    ]);

    $blocked = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(approvalFlowUrl($this->flow))
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($blocked->json('errors'))->toHaveKey('code');

    $request->update(['status' => ApprovalRequest::STATUS_APPROVED]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(approvalFlowUrl($this->flow))
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(ApprovalFlow::query()->find($this->flow->getKey()))->toBeNull();
    expect(ApprovalFlow::withTrashed()->find($this->flow->getKey()))->not->toBeNull();
});

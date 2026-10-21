<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Approvals\Services\ApprovalSubjectRegistry;
use Modules\Organization\Models\Entity;
use Modules\Payments\Listeners\SyncPaymentApprovalStatus;
use Modules\Payments\Models\CashRequest;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-PAY', 'name' => 'Entity PAY', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->firstApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->firstApprover->assignRole('staff');

    $this->secondApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->secondApprover->assignRole('staff');

    // Baseline approval configuration for cash requests, built the way
    // ApprovalTest builds the evaluation one: one setting, one 0 → null flow,
    // a record step plus two decide steps, and a TOCA band per approver.
    $this->setting = ApprovalSetting::create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'petty_cash_request',
        'name' => 'Petty Cash Request Approval',
        'is_active' => true,
    ]);

    $this->flow = ApprovalFlow::create([
        'entity_id' => $this->entity->getKey(),
        'approval_setting_id' => $this->setting->getKey(),
        'code' => 'petty-cash-request-test',
        'name' => 'Test flow',
        'min_amount' => 0,
        'max_amount' => null,
        'is_active' => true,
    ]);

    foreach ([
        [1, 'requested', 'Requested By', ApprovalStep::MODE_RECORD, null],
        [2, 'checked', 'Checked By', ApprovalStep::MODE_DECIDE, ['approve', 'reject', 'return']],
        [3, 'approved', 'Approved By', ApprovalStep::MODE_DECIDE, ['approve', 'reject', 'return']],
    ] as [$position, $key, $label, $mode, $actions]) {
        ApprovalStep::create([
            'entity_id' => $this->entity->getKey(),
            'approval_flow_id' => $this->flow->getKey(),
            'position' => $position,
            'key' => $key,
            'label' => $label,
            'action_mode' => $mode,
            'allowed_actions' => $actions,
            'show_on_print' => true,
        ]);
    }

    $firstEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'First approver band',
        'subject_type' => 'petty_cash_request',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);
    $firstEntry->users()->attach($this->firstApprover);

    $secondEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Second approver band',
        'subject_type' => 'petty_cash_request',
        'min_amount' => 0,
        'max_amount' => null,
    ]);
    $secondEntry->users()->attach($this->secondApprover);
});

function paymentsCashRequest(): TestResponse
{
    return test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/payments/petty-cash-requests', [
            'type' => 'petty_cash',
            'campus' => 'CEN',
            'division' => 'FIN',
            'department' => 'ACC',
            'request_date' => '2026-06-15',
            'exchange_rate' => 4026,
            'lines' => [
                ['description' => 'Office supplies', 'currency' => 'USD', 'amount' => 220],
                ['description' => 'Team lunch', 'currency' => 'KHR', 'amount' => 1500000],
            ],
        ]);
}

function paymentsApprovalsSubmit(string $subjectId, array $assignees, ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')->postJson('/api/v1/approvals/requests', [
        'subject_type' => 'petty_cash_request',
        'subject_id' => $subjectId,
        'assignees' => $assignees,
    ]);
}

function paymentsApprovalsAct(ApprovalRequest $request, string $action, User $user): TestResponse
{
    return test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", ['action' => $action]);
}

function paymentsApprovalsAssignees(): array
{
    return [
        2 => (int) test()->firstApprover->getKey(),
        3 => (int) test()->secondApprover->getKey(),
    ];
}

it('registers the five payment subjects with the payments.manage permission', function () {
    $types = ApprovalSubjectRegistry::types();

    $paymentTypes = array_values(array_diff($types, ['evaluation']));
    sort($paymentTypes);

    expect($types)->toContain('evaluation')
        ->toHaveCount(6)
        ->and($paymentTypes)->toBe([
            'advance_liquidation',
            'advance_request',
            'cash_clearance',
            'cash_request',
            'petty_cash_request',
        ])
        ->and(ApprovalSubjectRegistry::permission('petty_cash_request'))->toBe('payments.manage');
});

it('freezes a cash request snapshot with its header and lines', function () {
    $cashRequest = CashRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'campus' => 'CEN',
        'total_usd' => 592.58,
    ]);

    $cashRequest->lines()->createMany([
        [
            'entity_id' => $this->entity->getKey(),
            'description' => 'Office supplies',
            'currency' => 'USD',
            'amount' => 220,
            'amount_usd' => 220,
            'position' => 0,
        ],
        [
            'entity_id' => $this->entity->getKey(),
            'description' => 'Team lunch',
            'currency' => 'KHR',
            'amount' => 1500000,
            'amount_usd' => 372.58,
            'position' => 1,
        ],
    ]);

    $snapshot = app(ApprovalSubjectRegistry::class)
        ->documentSnapshot('petty_cash_request', $cashRequest);

    expect($snapshot)->toHaveKeys([
        'code',
        'type',
        'status',
        'campus',
        'division',
        'department',
        'requestDate',
        'totalUsd',
        'lines',
    ])
        ->and($snapshot['code'])->toBe($cashRequest->code)
        ->and($snapshot['type'])->toBe('petty_cash')
        ->and($snapshot['status'])->toBe('draft')
        ->and($snapshot['totalUsd'])->toBe('592.58')
        ->and($snapshot['lines'])->toBe([
            ['description' => 'Office supplies', 'currency' => 'USD', 'amount' => '220.00', 'amountUsd' => '220.00'],
            ['description' => 'Team lunch', 'currency' => 'KHR', 'amount' => '1500000.00', 'amountUsd' => '372.58'],
        ]);
});

it('rejects submitting a document under another cash request subject type', function () {
    $id = paymentsCashRequest()
        ->assertStatus(201)
        ->json('data.id');

    test()->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/requests', [
            'subject_type' => 'advance_request',
            'subject_id' => $id,
            'assignees' => paymentsApprovalsAssignees(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonPath('errors.subjectType.0', 'The document does not match this approval subject type.');

    // The round never opened and the document is untouched.
    expect(ApprovalRequest::query()->count())->toBe(0)
        ->and(CashRequest::query()->findOrFail($id)->status)->toBe('draft');
});

it('walks a cash request through approval and syncs its status', function () {
    $id = paymentsCashRequest()
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'draft')
        ->json('data.id');

    $approval = paymentsApprovalsSubmit($id, paymentsApprovalsAssignees())
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.currentStep.key', 'checked');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_review');

    $request = ApprovalRequest::query()->findOrFail($approval->json('data.id'));

    paymentsApprovalsAct($request, 'approve', $this->firstApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.currentStep.key', 'approved');

    paymentsApprovalsAct($request, 'approve', $this->secondApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.currentStep', null);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($request->fresh()->decided_at)->not->toBeNull();
});

it('maps approval outcomes onto the payment document status vocabulary', function () {
    $cashRequest = CashRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ]);

    $listener = app(SyncPaymentApprovalStatus::class);

    $handle = function (string $status) use ($listener, $cashRequest): void {
        $listener->handle(new ApprovalStatusChanged(
            'petty_cash_request',
            (string) $cashRequest->getKey(),
            $status,
            '01JXXXXXXXXXXXXXXXXXXXXXXX',
            (int) $this->admin->getKey(),
        ));
    };

    $handle('pending');
    expect($cashRequest->fresh()->status)->toBe('in_review');

    $handle('returned');
    expect($cashRequest->fresh()->status)->toBe('draft');

    $handle('rejected');
    expect($cashRequest->fresh()->status)->toBe('rejected');

    $handle('approved');
    expect($cashRequest->fresh()->status)->toBe('approved');
});

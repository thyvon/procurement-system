<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-AP', 'name' => 'Entity AP', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-AP2', 'name' => 'Entity AP2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->firstApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->firstApprover->assignRole('staff');

    $this->secondApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->secondApprover->assignRole('staff');

    $this->outsider = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->outsider->assignRole('staff');

    $this->setting = ApprovalSetting::create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'evaluation',
        'name' => 'Evaluation Approval',
        'is_active' => true,
    ]);

    $this->flow = ApprovalFlow::create([
        'entity_id' => $this->entity->getKey(),
        'approval_setting_id' => $this->setting->getKey(),
        'code' => 'evaluation-test',
        'name' => 'Test flow',
        'min_amount' => 0,
        'max_amount' => null,
        'is_active' => true,
    ]);

    foreach ([
        [1, 'prepared', 'Prepared By', ApprovalStep::MODE_RECORD, null],
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
        ]);
    }

    $firstEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'First approver band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);
    $firstEntry->users()->attach($this->firstApprover);

    $secondEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Second approver band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);
    $secondEntry->users()->attach($this->secondApprover);
});

function approvalsEvaluation(float $amount = 44, string $code = 'EVAL-26-001', ?Entity $entity = null): Evaluation
{
    $entity ??= test()->entity;

    return Evaluation::create([
        'entity_id' => $entity->getKey(),
        'code' => $code,
        'status' => 'draft',
        'recommendation_basis' => 'Best value for money.',
        'awarded_total' => $amount,
        'created_by' => test()->admin->getKey(),
    ]);
}

function approvalsAssignees(): array
{
    return [
        2 => (int) test()->firstApprover->getKey(),
        3 => (int) test()->secondApprover->getKey(),
    ];
}

function approvalsSubmit(Evaluation $evaluation, array $assignees, ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')->postJson('/api/v1/approvals/requests', [
        'subject_type' => 'evaluation',
        'subject_id' => (string) $evaluation->getKey(),
        'assignees' => $assignees,
    ]);
}

function approvalsAct(ApprovalRequest $request, string $action, ?string $comment = null, ?User $user = null): TestResponse
{
    $user ??= test()->firstApprover;

    $payload = ['action' => $action];

    if ($comment !== null) {
        $payload['comment'] = $comment;
    }

    return test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", $payload);
}

function approvalsPending(Evaluation $evaluation, array $assignees = []): ApprovalRequest
{
    $id = approvalsSubmit($evaluation, $assignees === [] ? approvalsAssignees() : $assignees)
        ->assertStatus(201)
        ->json('data.id');

    return ApprovalRequest::query()->findOrFail($id);
}

it('requires authentication', function () {
    $this->getJson('/api/v1/approvals/inbox')->assertStatus(401);
    $this->getJson('/api/v1/approvals/requests')->assertStatus(401);
});

it('forbids a user without approvals.view', function () {
    $viewerless = User::factory()->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($viewerless, 'sanctum')
        ->getJson('/api/v1/approvals/inbox')
        ->assertStatus(403);
});

it('previews the workflow with ordered steps and TOCA-filtered candidates', function () {
    $evaluation = approvalsEvaluation();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$evaluation->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.subjectType', 'evaluation')
        ->assertJsonPath('data.subjectId', $evaluation->getKey())
        ->assertJsonPath('data.documentCode', 'EVAL-26-001')
        ->assertJsonPath('data.amount', '44.00')
        ->assertJsonPath('data.flow.id', $this->flow->getKey())
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.steps.0.key', 'prepared')
        ->assertJsonPath('data.steps.0.actionMode', 'record')
        ->assertJsonPath('data.steps.1.actionMode', 'decide')
        ->assertJsonPath('data.steps.1.allowedActions.0', 'approve')
        ->assertJsonPath('data.steps.0.candidates', null);

    $candidateIds = collect($response->json('data.steps.1.candidates'))->pluck('id')->all();

    expect($candidateIds)
        ->toContain($this->firstApprover->getKey())
        ->toContain($this->secondApprover->getKey())
        ->not->toContain($this->outsider->getKey());
});

it('narrows preview candidates to the steps each user may act on', function () {
    TocaEntry::query()
        ->whereHas('users', fn ($query) => $query->whereKey($this->firstApprover->getKey()))
        ->update(['step_key' => 'checked']);
    TocaEntry::query()
        ->whereHas('users', fn ($query) => $query->whereKey($this->secondApprover->getKey()))
        ->update(['step_key' => 'approved']);

    // An entry without a step scope still qualifies for every step.
    $adminEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Admin band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);
    $adminEntry->users()->attach($this->admin);

    $steps = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/preview?subject_type=evaluation&subject_id='.approvalsEvaluation()->getKey())
        ->assertOk()
        ->json('data.steps');

    $checked = collect($steps[1]['candidates'])->pluck('id')->all();
    $approved = collect($steps[2]['candidates'])->pluck('id')->all();

    expect($checked)
        ->toContain($this->firstApprover->getKey())
        ->toContain($this->admin->getKey())
        ->not->toContain($this->secondApprover->getKey())
        ->and($approved)
        ->toContain($this->secondApprover->getKey())
        ->toContain($this->admin->getKey())
        ->not->toContain($this->firstApprover->getKey());
});

it('excludes inactive users from preview candidates', function () {
    $retired = User::factory()->create(['entity_id' => $this->entity->getKey(), 'is_active' => false]);

    $retiredEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Retired band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);
    $retiredEntry->users()->attach($retired);

    $candidateIds = collect(
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/approvals/preview?subject_type=evaluation&subject_id='.approvalsEvaluation()->getKey())
            ->assertOk()
            ->json('data.steps.1.candidates')
    )->pluck('id')->all();

    expect($candidateIds)->not->toContain($retired->getKey());
});

it('rejects a preview with an unknown subject type or an unknown record', function () {
    $unknownType = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/preview?subject_type=contract&subject_id=01J')
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($unknownType->json('errors'))->toHaveKey('subjectType');

    $missing = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/preview')
        ->assertStatus(422);

    // subject_id is optional (draft previews) — a missing request now needs
    // an amount instead of a subject_id.
    expect($missing->json('errors'))->toHaveKeys(['subject_type', 'amount']);
});

it('never previews another entity evaluation', function () {
    $foreign = approvalsEvaluation(44, 'EVAL-26-900', $this->otherEntity);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$foreign->getKey()}")
        ->assertStatus(422)
        ->assertJsonPath('errors.subjectId.0', 'Record not found.');
});

it('previews a draft amount override without writing the subject record', function () {
    $evaluation = approvalsEvaluation();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$evaluation->getKey()}&amount=1500")
        ->assertOk()
        ->assertJsonPath('data.amount', '1500.00')
        ->assertJsonPath('data.flow.id', $this->flow->getKey())
        ->assertJsonCount(3, 'data.steps');

    expect((float) $evaluation->fresh()->awarded_total)->toBe(44.0);

    $candidateIds = collect($response->json('data.steps.1.candidates'))->pluck('id')->all();

    expect($candidateIds)
        ->toContain($this->secondApprover->getKey())
        ->not->toContain($this->firstApprover->getKey());
});

it('resolves a different flow when the draft amount crosses the band', function () {
    $evaluation = approvalsEvaluation();

    $this->flow->update(['max_amount' => 1000]);

    $highValue = ApprovalFlow::create([
        'entity_id' => $this->entity->getKey(),
        'approval_setting_id' => $this->setting->getKey(),
        'code' => 'evaluation-high',
        'name' => 'High value flow',
        'min_amount' => 1001,
        'max_amount' => null,
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'entity_id' => $this->entity->getKey(),
        'approval_flow_id' => $highValue->getKey(),
        'position' => 1,
        'key' => 'executive',
        'label' => 'Executive Approval',
        'action_mode' => ApprovalStep::MODE_DECIDE,
        'allowed_actions' => ['approve', 'reject', 'return'],
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$evaluation->getKey()}&amount=1000")
        ->assertOk()
        ->assertJsonPath('data.amount', '1000.00')
        ->assertJsonPath('data.flow.id', $this->flow->getKey());

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$evaluation->getKey()}&amount=1001")
        ->assertOk()
        ->assertJsonPath('data.amount', '1001.00')
        ->assertJsonPath('data.flow.id', $highValue->getKey())
        ->assertJsonCount(1, 'data.steps')
        ->assertJsonPath('data.steps.0.key', 'executive');
});

it('rejects a draft amount that is not a non-negative number', function () {
    $evaluation = approvalsEvaluation();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/preview?subject_type=evaluation&subject_id={$evaluation->getKey()}&amount=-1")
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('previews a draft document that has not been saved yet from its amount alone', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/preview?subject_type=evaluation&amount=1500')
        ->assertOk()
        ->assertJsonPath('data.subjectType', 'evaluation')
        ->assertJsonPath('data.subjectId', null)
        ->assertJsonPath('data.documentCode', null)
        ->assertJsonPath('data.amount', '1500.00')
        ->assertJsonPath('data.flow.id', $this->flow->getKey())
        ->assertJsonCount(3, 'data.steps');

    $candidateIds = collect($response->json('data.steps.1.candidates'))->pluck('id')->all();

    expect($candidateIds)
        ->toContain($this->secondApprover->getKey())
        ->not->toContain($this->firstApprover->getKey());
});

it('requires an amount for a draft preview that references no document', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/preview?subject_type=evaluation')
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('amount');
});

it('submits an evaluation, stamps the record step and parks on the first decide step', function () {
    $evaluation = approvalsEvaluation();

    $response = approvalsSubmit($evaluation, approvalsAssignees())
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.subjectType', 'evaluation')
        ->assertJsonPath('data.documentCode', 'EVAL-26-001')
        ->assertJsonPath('data.currentStep.key', 'checked')
        ->assertJsonPath('data.currentStep.assigneeId', $this->firstApprover->getKey())
        ->assertJsonPath('data.currentStep.assigneeName', $this->firstApprover->name)
        ->assertJsonPath('data.flow.code', 'evaluation-test')
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.submittedBy', $this->admin->name);

    $request = ApprovalRequest::query()->findOrFail($response->json('data.id'));

    expect($request->snapshot['steps'])->toHaveCount(3)
        ->and($request->snapshot['steps'][1]['assigneeName'])->toBe($this->firstApprover->name)
        ->and($request->snapshot['steps'][2]['assigneeName'])->toBe($this->secondApprover->name)
        ->and($request->actions()->count())->toBe(1)
        ->and($request->actions()->first()->action)->toBe('record')
        ->and($request->actions()->first()->step_key)->toBe('prepared')
        ->and($request->current_position)->toBe(2)
        ->and($evaluation->fresh()->status)->toBe('in_review');
});

it('notifies the current assignee when a request is submitted', function () {
    approvalsPending(approvalsEvaluation());

    expect($this->firstApprover->notifications()->count())->toBe(1)
        ->and($this->secondApprover->notifications()->count())->toBe(0)
        ->and($this->firstApprover->notifications()->first()->data['type'])->toBe('approval.action_required')
        ->and($this->firstApprover->notifications()->first()->data['stepLabel'])->toBe('Checked By');
});

it('requires an assignee for every decide step', function () {
    $response = approvalsSubmit(approvalsEvaluation(), [2 => (int) $this->firstApprover->getKey()])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('assignees.3')
        ->and(ApprovalRequest::query()->count())->toBe(0);
});

it('rejects an assignee whose commitment authority does not cover the amount', function () {
    $response = approvalsSubmit(
        approvalsEvaluation(5000),
        [2 => (int) $this->firstApprover->getKey(), 3 => (int) $this->secondApprover->getKey()],
    )->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('assignees.2')
        ->and(ApprovalRequest::query()->count())->toBe(0);
});

it('rejects an assignee who is not scoped to that step', function () {
    TocaEntry::query()
        ->whereHas('users', fn ($query) => $query->whereKey($this->firstApprover->getKey()))
        ->update(['step_key' => 'approved']);

    $response = approvalsSubmit(
        approvalsEvaluation(),
        [2 => (int) $this->firstApprover->getKey(), 3 => (int) $this->secondApprover->getKey()],
    )->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('assignees.2')
        ->and(ApprovalRequest::query()->count())->toBe(0);
});

it('rejects a second pending request for the same document', function () {
    $evaluation = approvalsEvaluation();
    approvalsPending($evaluation);

    $response = approvalsSubmit($evaluation, approvalsAssignees())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors.subjectId.0'))->toBe('A pending approval request already exists for this document.')
        ->and(ApprovalRequest::query()->count())->toBe(1);
});

it('forbids submission without the subject module permission', function () {
    $staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $staff->assignRole('staff');

    approvalsSubmit(approvalsEvaluation(), approvalsAssignees(), $staff)
        ->assertStatus(403);

    expect(ApprovalRequest::query()->count())->toBe(0);
});

it('advances to the next decide step on approve and notifies the next assignee', function () {
    $request = approvalsPending(approvalsEvaluation());

    approvalsAct($request, 'approve', null, $this->firstApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.currentStep.key', 'approved')
        ->assertJsonPath('data.currentStep.assigneeId', $this->secondApprover->getKey());

    expect($this->secondApprover->notifications()->count())->toBe(1)
        ->and($this->secondApprover->notifications()->first()->data['stepLabel'])->toBe('Approved By')
        ->and($request->fresh()->actions()->pluck('action')->all())->toBe(['record', 'approve']);
});

it('approves the request and syncs the evaluation status', function () {
    $evaluation = approvalsEvaluation();
    $request = approvalsPending($evaluation);

    approvalsAct($request, 'approve', null, $this->firstApprover)->assertOk();

    approvalsAct($request, 'approve', null, $this->secondApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.currentStep', null);

    $final = $request->fresh();

    expect($final->decided_at)->not->toBeNull()
        ->and($final->current_assignee_id)->toBeNull()
        ->and($evaluation->fresh()->status)->toBe('approved')
        ->and($this->admin->notifications()->where('data', 'like', '%approval.result%')->count())->toBe(1);
});

it('rejects with a comment and syncs the evaluation to rejected', function () {
    $evaluation = approvalsEvaluation();
    $request = approvalsPending($evaluation);

    approvalsAct($request, 'reject', null, $this->firstApprover)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    approvalsAct($request, 'reject', 'Prices are above the budget.', $this->firstApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $final = $request->fresh();

    expect($final->actions()->where('action', 'reject')->first()->comment)
        ->toBe('Prices are above the budget.')
        ->and($evaluation->fresh()->status)->toBe('rejected');
});

it('returns the document and resets the evaluation to draft', function () {
    $evaluation = approvalsEvaluation();
    $request = approvalsPending($evaluation);

    approvalsAct($request, 'return', 'Missing quotation attachment.', $this->firstApprover)
        ->assertOk()
        ->assertJsonPath('data.status', 'returned');

    expect($evaluation->fresh()->status)->toBe('draft')
        ->and($request->fresh()->current_position)->toBeNull();
});

it('forbids acting when you are not the current assignee', function () {
    $request = approvalsPending(approvalsEvaluation());

    approvalsAct($request, 'approve', null, $this->secondApprover)->assertStatus(403);
    approvalsAct($request, 'approve', null, $this->outsider)->assertStatus(403);

    expect($request->fresh()->status)->toBe('pending');
});

it('re-checks the step scope when acting', function () {
    $request = approvalsPending(approvalsEvaluation());

    TocaEntry::query()
        ->whereHas('users', fn ($query) => $query->whereKey($this->firstApprover->getKey()))
        ->update(['step_key' => 'approved']);

    approvalsAct($request, 'approve', null, $this->firstApprover)->assertStatus(403);

    expect($request->fresh()->status)->toBe('pending')
        ->and($request->fresh()->current_position)->toBe(2);
});

it('rejects an action the current step does not allow', function () {
    ApprovalStep::query()
        ->where('approval_flow_id', $this->flow->getKey())
        ->where('key', 'checked')
        ->update(['allowed_actions' => ['approve']]);

    $request = approvalsPending(approvalsEvaluation());

    $response = approvalsAct($request, 'reject', 'Nope.', $this->firstApprover)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('action')
        ->and($request->fresh()->status)->toBe('pending');
});

it('cannot act on a request that is no longer pending', function () {
    $request = approvalsPending(approvalsEvaluation());

    approvalsAct($request, 'reject', 'Done with it.', $this->firstApprover)->assertOk();

    approvalsAct($request, 'approve', null, $this->firstApprover)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

it('lists only my pending requests in the inbox with a pending count', function () {
    approvalsPending(approvalsEvaluation());

    $otherEvaluation = approvalsEvaluation(80, 'EVAL-26-002');
    $otherRequest = approvalsSubmit($otherEvaluation, [
        2 => (int) $this->secondApprover->getKey(),
        3 => (int) $this->secondApprover->getKey(),
    ])->assertStatus(201);

    $inbox = $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.currentStep.key', 'checked');

    expect($inbox->json('data.0.id'))->not->toBe($otherRequest->json('data.id'));

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox/count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    $this->actingAs($this->secondApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox/count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('searches the inbox by document code', function () {
    approvalsPending(approvalsEvaluation(44, 'EVAL-26-001'));
    approvalsPending(approvalsEvaluation(80, 'EVAL-26-002'));

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox?search=EVAL-26-002')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.documentCode', 'EVAL-26-002');

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox?search=no-match')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('filters the request list by status and subject', function () {
    approvalsPending(approvalsEvaluation());

    $second = approvalsEvaluation(80, 'EVAL-26-002');
    approvalsPending($second);

    $pending = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/requests?status=pending')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $approved = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/requests?status=approved')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $bySearch = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/requests?search=EVAL-26-002')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.documentCode', 'EVAL-26-002')
        ->assertJsonPath('data.0.subjectId', $second->getKey());

    expect($pending->json('data'))->toHaveCount(2)
        ->and($approved->json('data'))->toBeEmpty();
});

it('shows a request with its action timeline', function () {
    $request = approvalsPending(approvalsEvaluation());

    approvalsAct($request, 'approve', null, $this->firstApprover)->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/requests/{$request->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.id', $request->getKey())
        ->assertJsonPath('data.steps', $request->fresh()->snapshot['steps'])
        ->assertJsonCount(2, 'data.actions')
        ->assertJsonPath('data.actions.0.action', 'record')
        ->assertJsonPath('data.actions.0.stepKey', 'prepared')
        ->assertJsonPath('data.actions.0.actor.name', $this->admin->name)
        ->assertJsonPath('data.actions.1.action', 'approve')
        ->assertJsonPath('data.actions.1.actor.name', $this->firstApprover->name);
});

it('never returns another entity approval request', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $foreignFlow = ApprovalFlow::create([
        'entity_id' => $this->otherEntity->getKey(),
        'approval_setting_id' => ApprovalSetting::create([
            'entity_id' => $this->otherEntity->getKey(),
            'subject_type' => 'evaluation',
            'name' => 'Foreign',
            'is_active' => true,
        ])->getKey(),
        'code' => 'evaluation-test',
        'name' => 'Foreign flow',
        'min_amount' => 0,
        'max_amount' => null,
        'is_active' => true,
    ]);

    $foreignRequest = ApprovalRequest::create([
        'entity_id' => $this->otherEntity->getKey(),
        'subject_type' => 'evaluation',
        'subject_id' => (string) Str::ulid(),
        'approval_setting_id' => $foreignFlow->approval_setting_id,
        'approval_flow_id' => $foreignFlow->getKey(),
        'amount_snapshot' => 100,
        'status' => ApprovalRequest::STATUS_PENDING,
        'current_position' => 2,
        'snapshot' => ['documentCode' => 'EVAL-26-777', 'steps' => []],
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/requests/{$foreignRequest->getKey()}")
        ->assertNotFound();

    $codes = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/requests')
        ->assertOk()
        ->json('data.*.documentCode');

    expect($codes)->not->toContain('EVAL-26-777');
});

it('freezes the evaluation while its approval is pending', function () {
    $evaluation = approvalsEvaluation();
    approvalsPending($evaluation);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}")
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'This evaluation is pending approval and cannot be deleted.');

    expect($evaluation->fresh()->deleted_at)->toBeNull();
});

it('guards the outbox like the inbox', function () {
    $this->getJson('/api/v1/approvals/outbox')->assertStatus(401);

    $viewerless = User::factory()->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($viewerless, 'sanctum')
        ->getJson('/api/v1/approvals/outbox')
        ->assertStatus(403);
});

it('lists only my submissions in the outbox', function () {
    approvalsPending(approvalsEvaluation(44, 'EVAL-26-001'));
    approvalsPending(approvalsEvaluation(80, 'EVAL-26-002'));

    $this->firstApprover->givePermissionTo('evaluations.manage');
    approvalsSubmit(approvalsEvaluation(60, 'EVAL-26-003'), approvalsAssignees(), $this->firstApprover)
        ->assertStatus(201);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/outbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.submittedBy', $this->admin->name);

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/outbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.documentCode', 'EVAL-26-003')
        ->assertJsonPath('data.0.submittedBy', $this->firstApprover->name);

    $this->actingAs($this->outsider, 'sanctum')
        ->getJson('/api/v1/approvals/outbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('keeps decided requests in the inbox of whoever acted on them', function () {
    $request = approvalsPending(approvalsEvaluation());

    approvalsAct($request, 'approve', null, $this->firstApprover)->assertOk();

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($this->secondApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    approvalsAct($request, 'approve', null, $this->secondApprover)->assertOk();

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'approved');

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox?status=pending')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson('/api/v1/approvals/inbox?status=approved')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('filters the tray by status, subject type and date', function () {
    approvalsPending(approvalsEvaluation());

    $today = now()->format('Y-m-d');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/approvals/outbox?status=pending&subject_type=evaluation&date_from={$today}&date_to={$today}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/outbox?status=approved')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/outbox?subject_type=purchase_order')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/outbox?date_from='.now()->addDay()->format('Y-m-d'))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($this->firstApprover, 'sanctum')
        ->getJson("/api/v1/approvals/inbox?status=pending&date_from={$today}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/approvals/requests?date_from=2026-01-02&date_to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

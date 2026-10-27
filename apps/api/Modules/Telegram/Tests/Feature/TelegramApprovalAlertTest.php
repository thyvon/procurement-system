<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\Telegram\Jobs\SendTelegramAlert;
use Modules\Telegram\Models\TelegramMessage;
use Modules\Telegram\Services\TelegramGateway;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.alerts_enabled' => true,
    ]);

    $this->entity = Entity::create(['code' => 'ENT-TGA', 'name' => 'Entity TGA', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->firstApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->firstApprover->assignRole('staff');

    $this->secondApprover = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->secondApprover->assignRole('staff');

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
            'show_on_print' => true,
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

    $this->fakeTelegramOk = function () {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 555],
            ]),
        ]);
    };
});

function tgEvaluation(float $amount = 44, string $code = 'EVAL-26-001'): Evaluation
{
    return Evaluation::create([
        'entity_id' => test()->entity->getKey(),
        'code' => $code,
        'status' => 'draft',
        'recommendation_basis' => 'Best value for money.',
        'awarded_total' => $amount,
        'created_by' => test()->admin->getKey(),
    ]);
}

function tgAssignees(): array
{
    return [
        2 => (int) test()->firstApprover->getKey(),
        3 => (int) test()->secondApprover->getKey(),
    ];
}

function tgSubmit(Evaluation $evaluation, array $assignees): string
{
    return (string) test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/approvals/requests', [
            'subject_type' => 'evaluation',
            'subject_id' => (string) $evaluation->getKey(),
            'assignees' => $assignees,
        ])
        ->assertStatus(201)
        ->json('data.id');
}

function tgPending(Evaluation $evaluation): ApprovalRequest
{
    $id = tgSubmit($evaluation, tgAssignees());

    return ApprovalRequest::query()->findOrFail($id);
}

function tgAct(ApprovalRequest $request, string $action, ?User $user = null): void
{
    test()->actingAs($user ?? test()->firstApprover, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", [
            'action' => $action,
            ...($action === 'approve' ? [] : ['comment' => 'Needs work.']),
        ])
        ->assertOk();
}

it('sends an action-required alert to the linked assignee on submit', function () {
    ($this->fakeTelegramOk)();
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    $requestId = tgSubmit(tgEvaluation(), tgAssignees());

    $message = TelegramMessage::sole();

    expect($message->status)->toBe(TelegramMessage::STATUS_SENT)
        ->and($message->direction)->toBe(TelegramMessage::DIRECTION_OUT)
        ->and($message->telegram_account_id)->toBeNull()
        ->and($message->telegram_contact_id)->toBeNull()
        ->and($message->telegram_chat_id)->toBe(777001)
        ->and($message->telegram_message_id)->toBe(555)
        ->and($message->reference_type)->toBe('approval_request')
        ->and($message->reference_id)->toBe((string) $requestId)
        ->and($message->body)->toContain('Approval action required')
        ->and($message->body)->toContain('EVAL-26-001')
        ->and($message->body)->toContain('44.00 USD')
        ->and($message->body)->toContain('Checked By')
        ->and($message->idempotency_key)->toStartWith('approval-alert:');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/bottest-bot-token/sendMessage')
            && $request['chat_id'] === 777001
            && str_contains($request['text'], 'Checked By')
            && $request->hasHeader('Authorization', 'Bearer test-bot-token');
    });

    // The in-app bell keeps working alongside the alert.
    expect($this->firstApprover->notifications()->count())->toBe(1);
});

it('keeps only the in-app notification for a user with no linked chat', function () {
    ($this->fakeTelegramOk)();

    tgSubmit(tgEvaluation(), tgAssignees());

    expect(TelegramMessage::query()->count())->toBe(0)
        ->and($this->firstApprover->notifications()->count())->toBe(1);

    Http::assertSentCount(0);
});

it('keeps only the in-app notification when no bot token is configured', function () {
    ($this->fakeTelegramOk)();
    config(['services.telegram.bot_token' => null]);
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    tgSubmit(tgEvaluation(), tgAssignees());

    expect(TelegramMessage::query()->count())->toBe(0)
        ->and($this->firstApprover->notifications()->count())->toBe(1);

    Http::assertSentCount(0);
});

it('honours the alerts kill switch without touching the database channel', function () {
    ($this->fakeTelegramOk)();
    config(['services.telegram.alerts_enabled' => false]);
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    tgSubmit(tgEvaluation(), tgAssignees());

    expect(TelegramMessage::query()->count())->toBe(0)
        ->and($this->firstApprover->notifications()->count())->toBe(1);

    Http::assertSentCount(0);
});

it('dispatches the delivery job on the telegram queue', function () {
    Queue::fake();
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    tgSubmit(tgEvaluation(), tgAssignees());

    Queue::assertPushed(
        SendTelegramAlert::class,
        fn (SendTelegramAlert $job): bool => $job->chatId === 777001
            && $job->queue === 'telegram'
            && TelegramMessage::query()->where('status', TelegramMessage::STATUS_QUEUED)->exists(),
    );
});

it('sends the result alert to the submitter when the round is approved', function () {
    ($this->fakeTelegramOk)();
    $this->admin->telegram_chat_id = 888001;
    $this->admin->save();

    $request = tgPending(tgEvaluation());
    tgAct($request, 'approve');
    tgAct($request, 'approve', $this->secondApprover);

    $message = TelegramMessage::sole();

    expect($message->telegram_chat_id)->toBe(888001)
        ->and($message->body)->toContain('Approval result: approved')
        ->and($message->body)->toContain('EVAL-26-001');

    expect($this->admin->notifications()->where('data', 'like', '%approval.result%')->count())->toBe(1);
});

it('alerts the assignee their task is gone when the request is cancelled', function () {
    ($this->fakeTelegramOk)();
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    $request = tgPending(tgEvaluation());

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/cancel")
        ->assertOk();

    $bodies = TelegramMessage::query()->pluck('body');

    expect($bodies)->toHaveCount(2)
        ->and($bodies->join("\n"))->toContain('Approval result: cancelled');
});

it('marks the alert failed when the bot API rejects it permanently', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ], 403),
    ]);
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    tgSubmit(tgEvaluation(), tgAssignees());

    $message = TelegramMessage::sole();

    expect($message->status)->toBe(TelegramMessage::STATUS_FAILED)
        ->and($message->error)->toContain('bot was blocked');
});

it('keeps the alert queued when Telegram flood-waits it', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests: retry after 7',
            'parameters' => ['retry_after' => 7],
        ], 429),
    ]);
    $this->firstApprover->telegram_chat_id = 777001;
    $this->firstApprover->save();

    tgSubmit(tgEvaluation(), tgAssignees());

    expect(TelegramMessage::sole()->status)->toBe(TelegramMessage::STATUS_QUEUED);
});

it('replays one idempotency key without queueing a second alert', function () {
    ($this->fakeTelegramOk)();

    $gateway = app(TelegramGateway::class);
    $first = $gateway->sendBotAlert(777001, 'Same alert', 'alert-key-1', $this->entity->getKey());
    $second = $gateway->sendBotAlert(777001, 'Same alert', 'alert-key-1', $this->entity->getKey());

    expect($second->is($first))->toBeTrue()
        ->and(TelegramMessage::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

it('never sends the same alert twice when delivery runs again', function () {
    ($this->fakeTelegramOk)();

    $gateway = app(TelegramGateway::class);
    $message = $gateway->sendBotAlert(777001, 'At-least-once', 'alert-key-2', $this->entity->getKey());

    // The sync job delivers on a fresh instance — re-read the persisted row.
    expect($message->refresh()->status)->toBe(TelegramMessage::STATUS_SENT);

    $outcome = $gateway->deliverBotAlert($message->getKey());

    expect($outcome->status)->toBe('sent');

    Http::assertSentCount(1);
});

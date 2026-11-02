<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\Telegram\Jobs\ProcessTelegramActionReply;
use Modules\Telegram\Jobs\ProcessTelegramCallback;
use Modules\Telegram\Jobs\ProcessTelegramLinkStart;
use Modules\Telegram\Models\TelegramMessage;
use Spatie\Permission\Models\Role;

const CALLBACK_BOT_SECRET = 'callback-bot-secret';

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.bot_webhook_secret' => CALLBACK_BOT_SECRET,
        'services.telegram.alerts_enabled' => true,
        'services.telegram.alert_pdf_enabled' => false,
        'services.telegram.alert_actions_enabled' => false,
    ]);

    $this->entity = Entity::create(['code' => 'ENT-CBK', 'name' => 'Callback Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->approver = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->approver->assignRole('staff');

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
        'code' => 'evaluation-callback',
        'name' => 'Callback flow',
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
        'name' => 'Callback first band',
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);
    $firstEntry->users()->attach($this->approver);

    $secondEntry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'name' => 'Callback second band',
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

function cbEvaluation(float $amount = 44, string $code = 'EVAL-26-001'): Evaluation
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

function cbPending(): ApprovalRequest
{
    $id = (string) test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/approvals/requests', [
            'subject_type' => 'evaluation',
            'subject_id' => (string) cbEvaluation()->getKey(),
            'assignees' => [
                2 => (int) test()->approver->getKey(),
                3 => (int) test()->secondApprover->getKey(),
            ],
        ])
        ->assertStatus(201)
        ->json('data.id');

    return ApprovalRequest::query()->findOrFail($id);
}

function cbTap(ApprovalRequest $request, string $data, int $updateId = 900): mixed
{
    return cbPostWebhook([
        'update_id' => $updateId,
        'callback_query' => [
            'id' => "cbq{$updateId}",
            'chat_instance' => 'chat-instance',
            'from' => ['id' => 777001],
            'data' => $data,
            'message' => [
                'message_id' => 500,
                'chat' => ['id' => 777001, 'type' => 'private'],
                'text' => 'Alert body',
            ],
        ],
    ]);
}

function cbReply(string $text, int $updateId = 901): mixed
{
    return cbPostWebhook([
        'update_id' => $updateId,
        'message' => [
            'message_id' => 901,
            'from' => ['id' => 777001],
            'chat' => ['id' => 777001, 'type' => 'private'],
            'text' => $text,
            'reply_to_message' => ['message_id' => 555],
        ],
    ]);
}

function cbPostWebhook(array $payload): mixed
{
    return test()
        ->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => CALLBACK_BOT_SECRET])
        ->postJson('/api/internal/telegram/bot', $payload);
}

function cbLinkApprover(): void
{
    test()->approver->telegram_chat_id = 777001;
    test()->approver->save();
}

it('accepts a callback query and queues the tap job', function () {
    Queue::fake();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:approve")
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    Queue::assertPushed(
        ProcessTelegramCallback::class,
        fn (ProcessTelegramCallback $job): bool => str_ends_with($job->payload['callback_query']['data'] ?? '', ':approve'),
    );
    Queue::assertNotPushed(ProcessTelegramLinkStart::class);
});

it('routes a chat message to the reply job only while a prompt is pending', function () {
    Queue::fake();

    cbReply('Some text while nothing is pending')->assertOk();
    Queue::assertPushed(ProcessTelegramLinkStart::class);
    Queue::assertNotPushed(ProcessTelegramActionReply::class);

    Queue::fake();
    Cache::put('telegram:approval-action:777001', ['request_id' => 'x', 'action' => 'reject'], now()->addMinutes(10));

    cbReply('Not enough budget.')->assertOk();
    Queue::assertPushed(ProcessTelegramActionReply::class);
    Queue::assertNotPushed(ProcessTelegramLinkStart::class);
});

it('acts on approve immediately and answers the callback', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();
    $position = $request->current_position;

    cbTap($request, "approval:{$request->getKey()}:approve")->assertOk();

    $request->refresh();

    expect($request->actions()->where('action', 'approve')->count())->toBe(1)
        ->and($request->current_position)->toBe(3)
        ->and($request->current_position)->not->toBe($position)
        ->and(Cache::has('telegram:approval-action:777001'))->toBeFalse();

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains($http->body(), 'callback_query_id')
        && str_contains($http->body(), 'Approved.'));

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/editMessageReplyMarkup')
        && ($http->data()['message_id'] ?? null) === 500);
});

it('prompts for a reason when reject is tapped', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:reject")->assertOk();

    $prompt = TelegramMessage::where('reference_type', 'telegram_action_prompt')->sole();

    expect($prompt->body)->toContain('Reject')
        ->and($prompt->body)->toContain('EVAL-26-001')
        ->and($prompt->status)->toBe(TelegramMessage::STATUS_SENT)
        ->and($request->refresh()->status)->toBe(ApprovalRequest::STATUS_PENDING);

    $state = Cache::get('telegram:approval-action:777001');

    expect($state)->toBeArray()
        ->and($state['action'])->toBe('reject')
        ->and($state['request_id'])->toBe($request->getKey())
        ->and($state['alert_message_id'])->toBe(500);

    Http::assertSent(function ($http): bool {
        if (! str_contains($http->url(), '/sendMessage')) {
            return false;
        }

        $markup = json_decode($http->data()['reply_markup'] ?? '', true);

        return is_array($markup)
            && ($markup['force_reply'] ?? null) === true
            && ($markup['inline_keyboard'][0][0]['callback_data'] ?? null) === 'approval:discard';
    });

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains(urldecode($http->body()), 'Send the reason in your reply.'));
});

it('records the rejection with the replied reason and confirms it', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:reject")->assertOk();
    cbReply('Not enough budget.')->assertOk();

    $request->refresh();

    expect($request->status)->toBe(ApprovalRequest::STATUS_REJECTED)
        ->and($request->actions()->where('action', 'reject')->where('comment', 'Not enough budget.')->count())->toBe(1)
        ->and(Cache::has('telegram:approval-action:777001'))->toBeFalse();

    $result = TelegramMessage::where('reference_type', 'telegram_action_result')->sole();

    expect($result->body)->toContain('Rejected')
        ->and($result->body)->toContain('EVAL-26-001');

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/editMessageReplyMarkup')
        && ($http->data()['message_id'] ?? null) === 500);
});

it('clears a pending prompt when Cancel is tapped', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:reject")->assertOk();
    expect(Cache::has('telegram:approval-action:777001'))->toBeTrue();

    cbTap($request, 'approval:discard', 902)->assertOk();

    expect(Cache::has('telegram:approval-action:777001'))->toBeFalse();

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains($http->body(), 'Cancelled.'));
});

it('tells an unlinked chat to link first without acting', function () {
    ($this->fakeTelegramOk)();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:approve")->assertOk();

    expect($request->refresh()->actions()->where('action', 'approve')->count())->toBe(0);

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains(urldecode($http->body()), 'not linked'));
});

it('refuses the tap from a chat that is not the responsible approver', function () {
    ($this->fakeTelegramOk)();
    $this->admin->telegram_chat_id = 777001;
    $this->admin->save();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:approve")->assertOk();

    expect($request->refresh()->actions()->where('action', 'approve')->count())->toBe(0);

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains(urldecode($http->body()), 'responsible user'));
});

it('answers with no longer pending when a decided request is tapped', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();

    test()->actingAs($this->approver, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", ['action' => 'approve'])
        ->assertOk();
    test()->actingAs($this->secondApprover, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", ['action' => 'approve'])
        ->assertOk();

    expect($request->refresh()->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    cbTap($request, "approval:{$request->getKey()}:approve")->assertOk();

    expect($request->refresh()->actions()->where('action', 'approve')->count())->toBe(2);

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/answerCallbackQuery')
        && str_contains(urldecode($http->body()), 'no longer pending'));
});

it('keeps /start working while a prompt is pending', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();
    $request = cbPending();

    cbTap($request, "approval:{$request->getKey()}:reject")->assertOk();

    cbPostWebhook([
        'update_id' => 905,
        'message' => [
            'message_id' => 905,
            'from' => ['id' => 777001],
            'chat' => ['id' => 777001, 'type' => 'private'],
            'text' => '/start SOME-LINK-CODE',
        ],
    ])->assertOk();

    expect($request->refresh()->status)->toBe(ApprovalRequest::STATUS_PENDING)
        ->and(Cache::has('telegram:approval-action:777001'))->toBeTrue()
        ->and($request->actions()->where('action', 'reject')->count())->toBe(0)
        ->and(TelegramMessage::where('reference_type', 'telegram_action_result')->count())->toBe(0);
});

it('attaches the action buttons to a pending alert when the flag is on', function () {
    config(['services.telegram.alert_actions_enabled' => true]);
    ($this->fakeTelegramOk)();
    cbLinkApprover();

    cbPending();

    Http::assertSent(function ($http): bool {
        if (! str_contains($http->url(), '/sendMessage')) {
            return false;
        }

        $markup = json_decode($http->data()['reply_markup'] ?? '', true);

        return is_array($markup)
            && ($markup['inline_keyboard'][0][0]['text'] ?? null) === '✅ Approve'
            && str_contains($markup['inline_keyboard'][0][0]['callback_data'] ?? '', 'approval:')
            && ($markup['inline_keyboard'][1][0]['text'] ?? null) === '❌ Reject'
            && ($markup['inline_keyboard'][1][1]['text'] ?? null) === '↩️ Return';
    });
});

it('omits the buttons when the flag is off', function () {
    ($this->fakeTelegramOk)();
    cbLinkApprover();

    cbPending();

    Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
        && ! array_key_exists('reply_markup', $http->data()));
});

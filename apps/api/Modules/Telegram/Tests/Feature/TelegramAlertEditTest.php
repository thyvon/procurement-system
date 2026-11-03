<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Approvals\Services\ApprovalDocumentPrinter;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\Telegram\Models\TelegramMessage;
use Spatie\Permission\Models\Role;

/**
 * In-place rewriting of delivered approval alerts after a decision — the
 * same outcome whether the user decides in the web app or taps a Telegram
 * button (both run ApprovalService::act()/cancel()).
 */
beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.alerts_enabled' => true,
        'services.telegram.alert_edits_enabled' => true,
    ]);

    $this->entity = Entity::create(['code' => 'ENT-TGE', 'name' => 'Entity TGE', 'timezone' => 'UTC', 'locale' => 'en']);

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

    $this->telegram = new \stdClass;
    $this->telegram->calls = [];
    $this->telegram->nextMessageId = 500;
});

/**
 * Records every Bot API call: edit* calls land in $state->calls (url +
 * payload), sends return an incrementing message_id so rows stay tellable
 * apart, everything else answers ok.
 */
function teFakeTelegram(object $state): void
{
    Http::fake(function ($request) use ($state) {
        $url = $request->url();

        if (str_contains($url, 'editMessage')) {
            $state->calls[] = ['url' => $url, 'payload' => $request->data()];

            return Http::response(['ok' => true, 'result' => ['message_id' => 555]]);
        }

        if (str_contains($url, 'sendMessage') || str_contains($url, 'sendDocument')) {
            $state->nextMessageId++;

            return Http::response(['ok' => true, 'result' => ['message_id' => $state->nextMessageId]]);
        }

        return Http::response(['ok' => true, 'result' => ['message_id' => 555]]);
    });
}

function teEvaluation(): Evaluation
{
    return Evaluation::create([
        'entity_id' => test()->entity->getKey(),
        'code' => 'EVAL-26-901',
        'status' => 'draft',
        'recommendation_basis' => 'Best value for money.',
        'awarded_total' => 44,
        'created_by' => test()->admin->getKey(),
    ]);
}

function teLinkApprover(User $user, int $chatId): void
{
    $user->telegram_chat_id = $chatId;
    $user->save();
}

function teSubmit(array $assignees): ApprovalRequest
{
    $id = (string) test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/approvals/requests', [
            'subject_type' => 'evaluation',
            'subject_id' => (string) teEvaluation()->getKey(),
            'assignees' => $assignees,
        ])
        ->assertStatus(201)
        ->json('data.id');

    return ApprovalRequest::query()->findOrFail($id);
}

function teAct(ApprovalRequest $request, string $action, ?User $user = null, ?string $comment = null): void
{
    test()->actingAs($user ?? test()->firstApprover, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/actions", [
            'action' => $action,
            ...($comment !== null ? ['comment' => $comment] : []),
        ])
        ->assertOk();
}

it('rewrites only the actor alert when approving leaves the round open', function () {
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);
    teLinkApprover($this->secondApprover, 777002);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey(), (int) $this->secondApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    expect(TelegramMessage::query()->count())->toBe(2);

    teAct($request, 'approve', $this->firstApprover);

    $actorRow = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();
    $coSignerRow = TelegramMessage::query()->where('telegram_chat_id', 777002)->sole();

    expect($actorRow->body)->toContain('✅ You approved this request')
        ->and($actorRow->body)->toContain('EVAL-26-901')
        ->and($actorRow->parsed_json['edited_at'] ?? null)->not->toBeNull()
        ->and($coSignerRow->body)->toContain('🔔 Approval action required')
        ->and($coSignerRow->parsed_json['edited_at'] ?? null)->toBeNull();

    expect($this->telegram->calls)->toHaveCount(1);

    $edit = $this->telegram->calls[0];

    expect($edit['url'])->toContain('/editMessageText')
        ->and($edit['payload']['message_id'])->toBe($actorRow->telegram_message_id)
        ->and($edit['payload']['text'])->toContain('You approved this request')
        ->and($edit['payload']['reply_markup'])->toBe(json_encode(['inline_keyboard' => []]));
});

it('rewrites the alert with status decided-by and reason from the web app', function () {
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    teAct($request, 'reject', $this->firstApprover, 'Not enough budget.');

    $row = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();

    expect($row->body)->toContain('❌ Approval result: rejected')
        ->and($row->body)->toContain('👤 Decided by: '.$this->firstApprover->name)
        ->and($row->body)->toContain('💬 Reason: Not enough budget.')
        ->and($row->parsed_json['edited_at'] ?? null)->not->toBeNull();

    expect($this->telegram->calls)->toHaveCount(1)
        ->and($this->telegram->calls[0]['payload']['reply_markup'])->toBe(json_encode(['inline_keyboard' => []]));
});

it('rewrites every stale alert when the final approval closes the round', function () {
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);
    teLinkApprover($this->secondApprover, 777002);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    teAct($request, 'approve', $this->firstApprover);
    teAct($request, 'approve', $this->secondApprover);

    expect($request->refresh()->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    $firstRow = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();
    $secondRow = TelegramMessage::query()->where('telegram_chat_id', 777002)->sole();

    expect($firstRow->body)->toContain('✅ Approval result: approved')
        ->and($firstRow->body)->toContain('👤 Decided by: '.$this->secondApprover->name)
        ->and($secondRow->body)->toContain('✅ Approval result: approved')
        ->and($secondRow->body)->toContain('👤 Decided by: '.$this->secondApprover->name)
        ->and($secondRow->parsed_json['edited_at'] ?? null)->not->toBeNull();
});

it('rewrites the alert when the request is cancelled in the app', function () {
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/approvals/requests/{$request->getKey()}/cancel")
        ->assertOk();

    $rows = TelegramMessage::query()->orderBy('id')->get();

    // Row one: the action-required alert (edited in place). Row two: the
    // result notification created in the same transaction — never selected.
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->body)->toContain('🚫 Approval result: cancelled')
        ->and($rows[0]->body)->toContain('👤 Decided by: '.$this->admin->name)
        ->and($rows[0]->parsed_json['edited_at'] ?? null)->not->toBeNull()
        ->and($rows[1]->parsed_json['edited_at'] ?? null)->toBeNull();

    expect($this->telegram->calls)->toHaveCount(1);
});

it('edits the caption when the alert was delivered as a document', function () {
    config(['services.telegram.alert_pdf_enabled' => true]);
    $this->mock(ApprovalDocumentPrinter::class, fn ($printer) => $printer->shouldReceive('forRequest')
        ->once()
        ->andReturn(['binary' => '%PDF-1.4 test', 'filename' => 'EVAL-26-901.pdf']));
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    $row = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();
    expect($row->parsed_json['delivery'] ?? null)->toBe('document');

    teAct($request, 'reject', $this->firstApprover, 'No budget.');

    expect($this->telegram->calls)->toHaveCount(1);

    $edit = $this->telegram->calls[0];

    expect($edit['url'])->toContain('/editMessageCaption')
        ->and($edit['payload']['caption'])->toContain('❌ Approval result: rejected')
        ->and($row->refresh()->body)->toContain('❌ Approval result: rejected');
});

it('leaves the alerts untouched when the edit setting is off', function () {
    config(['services.telegram.alert_edits_enabled' => false]);
    teFakeTelegram($this->telegram);
    teLinkApprover($this->firstApprover, 777001);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    teAct($request, 'reject', $this->firstApprover, 'No budget.');

    $row = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();

    expect($this->telegram->calls)->toBeEmpty()
        ->and($row->body)->toContain('🔔 Approval action required')
        ->and($row->parsed_json['edited_at'] ?? null)->toBeNull();
});

it('treats an already-modified chat as a successful edit', function () {
    Http::fake([
        'https://api.telegram.org/*/editMessage*' => Http::response([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: message is not modified',
        ], 400),
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]]),
    ]);
    teLinkApprover($this->firstApprover, 777001);

    $request = teSubmit([
        '2' => [(int) $this->firstApprover->getKey()],
        '3' => [(int) $this->secondApprover->getKey()],
    ]);

    teAct($request, 'reject', $this->firstApprover, 'No budget.');

    $row = TelegramMessage::query()->where('telegram_chat_id', 777001)->sole();

    expect($row->status)->toBe(TelegramMessage::STATUS_SENT)
        ->and($row->body)->toContain('❌ Approval result: rejected')
        ->and($row->parsed_json['edited_at'] ?? null)->not->toBeNull();
});

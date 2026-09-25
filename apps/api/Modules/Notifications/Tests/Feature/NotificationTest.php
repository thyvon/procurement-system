<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Approvals\Notifications\ActionRequiredNotification;
use Modules\Approvals\Notifications\ApprovalResultNotification;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-NO', 'name' => 'Entity NO', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->user = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->user->assignRole('staff');

    $this->other = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->other->assignRole('staff');
});

function notificationsActionRequired(User $user, string $stepLabel = 'Checked By'): void
{
    $user->notify(new ActionRequiredNotification([
        'requestId' => '01J0000000000000000000APRV',
        'subjectType' => 'evaluation',
        'subjectId' => '01J0000000000000000000EVAL',
        'documentCode' => 'EVAL-26-001',
        'stepLabel' => $stepLabel,
        'amount' => '44.00',
    ]));
}

function notificationsResult(User $user, string $status = 'approved'): void
{
    $user->notify(new ApprovalResultNotification([
        'requestId' => '01J0000000000000000000APRV',
        'subjectType' => 'evaluation',
        'subjectId' => '01J0000000000000000000EVAL',
        'documentCode' => 'EVAL-26-001',
        'status' => $status,
        'amount' => '44.00',
    ]));
}

function notificationsOfType(User $user, string $type): DatabaseNotification
{
    return $user->notifications()->where('data', 'like', "%\"type\":\"{$type}\"%")->firstOrFail();
}

it('requires authentication', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
    $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
    $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
});

it('lists my notifications with unread ones first and pagination meta', function () {
    notificationsActionRequired($this->user);
    notificationsResult($this->user);
    notificationsOfType($this->user, 'approval.result')->markAsRead();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['page', 'perPage', 'total']])
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'approval.action_required')
        ->assertJsonPath('data.0.readAt', null)
        ->assertJsonPath('data.0.data.stepLabel', 'Checked By')
        ->assertJsonPath('data.1.type', 'approval.result');

    expect($response->json('data.1.readAt'))->not->toBeNull()
        ->and($response->json('data.0.createdAt'))->not->toBeNull();
});

it('never lists another user notifications', function () {
    notificationsActionRequired($this->other);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    expect($response->json('data'))->toBeEmpty();
});

it('counts only my unread notifications', function () {
    notificationsActionRequired($this->user);
    notificationsResult($this->user);
    notificationsOfType($this->user, 'approval.result')->markAsRead();
    notificationsActionRequired($this->other);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks a notification as read', function () {
    notificationsActionRequired($this->user);
    notificationsResult($this->user);

    $id = notificationsOfType($this->user, 'approval.action_required')->id;

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.type', 'approval.action_required');

    expect($response->json('data.readAt'))->not->toBeNull()
        ->and($this->user->fresh()->unreadNotifications()->count())->toBe(1);

    // Re-reading an already-read notification stays idempotent.
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    expect($this->user->fresh()->unreadNotifications()->count())->toBe(1);
});

it('returns 404 for a notification that is not mine', function () {
    notificationsActionRequired($this->other);
    $foreignId = notificationsOfType($this->other, 'approval.action_required')->id;

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/notifications/{$foreignId}/read")
        ->assertNotFound()
        ->assertJsonPath('statusCode', 404);

    expect($this->other->fresh()->unreadNotifications()->count())->toBe(1);
});

it('marks all of my notifications as read', function () {
    notificationsActionRequired($this->user);
    notificationsResult($this->user, 'rejected');
    notificationsActionRequired($this->other);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.read', true);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0);

    expect($this->other->fresh()->unreadNotifications()->count())->toBe(1);
});

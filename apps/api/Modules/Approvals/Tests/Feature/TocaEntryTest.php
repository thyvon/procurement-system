<?php

use App\Models\User;
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
        'user_id' => test()->approver->getKey(),
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ], $overrides);
}

function tocaEntryUrl(TocaEntry $entry): string
{
    return "/api/v1/approvals/toca-entries/{$entry->getKey()}";
}

it('requires authentication', function () {
    $this->getJson('/api/v1/approvals/toca-entries')->assertStatus(401);
});

it('lists authority rows with the user name for a viewer', function () {
    TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'user_id' => $this->approver->getKey(),
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => 1000,
    ]);

    $response = $this->actingAs($this->viewer, 'sanctum')
        ->getJson('/api/v1/approvals/toca-entries')
        ->assertOk();

    $row = $response->json('data.0');

    expect($row)->toHaveKeys(['id', 'userId', 'userName', 'subjectType', 'minAmount', 'maxAmount']);
    expect($row['userName'])->toBe($this->approver->name);
});

it('forbids staff from writing authority rows', function () {
    $this->actingAs($this->viewer, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload())
        ->assertStatus(403);
});

it('creates an authority row', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload())
        ->assertStatus(201);

    $row = $response->json('data');

    expect($row['userId'])->toBe($this->approver->getKey());
    expect($row['userName'])->toBe($this->approver->name);
    expect($row['maxAmount'])->toBe('1000.00');

    $stored = TocaEntry::query()->find($row['id']);
    expect((int) $stored->created_by)->toBe((int) $this->admin->getKey());
    expect((int) $stored->updated_by)->toBe((int) $this->admin->getKey());
});

it('rejects an unknown subject type, an unknown user and an inverted band', function () {
    $unknownType = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['subject_type' => 'contract']))
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($unknownType->json('errors'))->toHaveKey('subject_type');

    $unknownUser = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['user_id' => 999999]))
        ->assertStatus(422);

    expect($unknownUser->json('errors'))->toHaveKey('user_id');

    $inverted = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/approvals/toca-entries', tocaPayload(['min_amount' => 500, 'max_amount' => 100]))
        ->assertStatus(422);

    expect($inverted->json('errors'))->toHaveKey('max_amount');
});

it('updates an authority row', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'user_id' => $this->approver->getKey(),
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

it('deletes an authority row', function () {
    $entry = TocaEntry::create([
        'entity_id' => $this->entity->getKey(),
        'user_id' => $this->approver->getKey(),
        'subject_type' => 'evaluation',
        'min_amount' => 0,
        'max_amount' => null,
    ]);

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

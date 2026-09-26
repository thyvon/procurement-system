<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\EPurchase\Services\EPurchaseClient;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;
use Modules\EPurchase\Services\InvalidCompanyCredentialsException;

beforeEach(function () {
    Http::preventStrayRequests();
});

function epurchaseClientSuccessPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'result' => 'success',
        'msg' => 'Login successfully',
        'data' => 'company-session-jwt',
        'formToken' => 'form-token-abc',
        'userPhoto' => 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'),
        'user' => [
            'id' => 1963,
            'card_id' => '3665',
            'name' => 'Vun Thy',
            'username' => '3665',
            'email' => 'vun.thy@mjqeducation.edu.kh',
        ],
    ], $overrides);
}

it('maps a successful company login response to a result object', function () {
    Http::fake(['*' => Http::response(epurchaseClientSuccessPayload([
        'user' => ['real_position' => '  Procurement Officer  '],
    ]))]);

    $result = app(EPurchaseClient::class)->login('3665', 'secret-company-password');

    expect($result->email)->toBe('vun.thy@mjqeducation.edu.kh')
        ->and($result->name)->toBe('Vun Thy')
        ->and($result->jwt)->toBe('company-session-jwt')
        ->and($result->formToken)->toBe('form-token-abc')
        ->and($result->position)->toBe('Procurement Officer')
        ->and($result->userPhoto)->toBe('data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/default_user_access/login')
            && $request['employee_id'] === '3665'
            && $request['password'] === 'secret-company-password';
    });
});

it('maps an absent or empty real_position to null', function () {
    Http::fake(['*' => Http::response(epurchaseClientSuccessPayload(['user' => ['real_position' => '   ']]))]);

    expect(app(EPurchaseClient::class)->login('3665', 'secret-company-password')->position)->toBeNull();

    $payload = epurchaseClientSuccessPayload();
    unset($payload['user']['real_position']);
    Http::fake(['*' => Http::response($payload)]);

    expect(app(EPurchaseClient::class)->login('3665', 'secret-company-password')->position)->toBeNull();
});

it('maps an absent formToken to null', function () {
    $payload = epurchaseClientSuccessPayload();
    unset($payload['formToken']);
    Http::fake(['*' => Http::response($payload)]);

    expect(app(EPurchaseClient::class)->login('3665', 'secret-company-password')->formToken)->toBeNull();
});

it('maps an absent or empty userPhoto to null', function () {
    Http::fake(['*' => Http::response(epurchaseClientSuccessPayload(['userPhoto' => '']))]);

    expect(app(EPurchaseClient::class)->login('3665', 'secret-company-password')->userPhoto)->toBeNull();

    $payload = epurchaseClientSuccessPayload();
    unset($payload['userPhoto']);
    Http::fake(['*' => Http::response($payload)]);

    expect(app(EPurchaseClient::class)->login('3665', 'secret-company-password')->userPhoto)->toBeNull();
});

it('rejects invalid credentials returned by the company system', function () {
    Http::fake(['*' => Http::response([
        'result' => 'failed',
        'msg' => 'Login failed',
    ])]);

    expect(fn () => app(EPurchaseClient::class)->login('3665', 'wrong-password'))
        ->toThrow(InvalidCompanyCredentialsException::class);
});

it('reports the company system as unavailable on connection failure', function () {
    Http::fake(['*' => Http::failedConnection()]);

    expect(fn () => app(EPurchaseClient::class)->login('3665', 'secret-company-password'))
        ->toThrow(EPurchaseUnavailableException::class);
});

it('reports the company system as unavailable when the payload has no result field', function () {
    Http::fake(['*' => Http::response(['message' => 'Server Error'], 500)]);

    expect(fn () => app(EPurchaseClient::class)->login('3665', 'secret-company-password'))
        ->toThrow(EPurchaseUnavailableException::class);
});

it('reports the company system as unavailable when the user payload has no email', function () {
    Http::fake(['*' => Http::response(epurchaseClientSuccessPayload([
        'user' => ['email' => null],
    ]))]);

    expect(fn () => app(EPurchaseClient::class)->login('3665', 'secret-company-password'))
        ->toThrow(EPurchaseUnavailableException::class);
});

it('fetches a page of company items with session headers', function () {
    Http::fake([
        '*/items-master-list*' => Http::response([
            'recordsTotal' => 2,
            'recordsFiltered' => 1,
            'data' => [
                ['ItemCode' => 'ITM-1', 'Description' => 'Paper A4', 'Status' => 'Active'],
                ['ItemCode' => '', 'Description' => ''],
            ],
        ]),
    ]);

    $session = new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    );

    $result = app(EPurchaseClient::class)->items($session, 0, 10, 'paper');

    expect($result['recordsFiltered'])->toBe(1)
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['ItemCode'])->toBe('ITM-1');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/items-master-list')
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request->hasHeader('Cookie', 'laravel_session=abc')
            && $request['start'] === '0'
            && $request['length'] === '10'
            && $request['search[value]'] === 'paper'
            && $request['_token'] === 'form-token-abc';
    });
});

it('throws session expired when items returns 401', function () {
    Http::fake(['*/items-master-list*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $session = new EPurchaseSession(
        jwt: 'stale-jwt',
        formToken: null,
        cookieHeader: '',
        expiresAt: time() + 600,
    );

    expect(fn () => app(EPurchaseClient::class)->items($session, 0, 10))
        ->toThrow(EPurchaseSessionExpiredException::class);
});

it('fetches a page of company suppliers with session headers', function () {
    Http::fake([
        '*/suppliers-master-list*' => Http::response([
            'recordsTotal' => 536,
            'recordsFiltered' => 2,
            'data' => [
                ['code' => 'SUP-00055', 'company_name' => 'Acme', 'status' => 1],
                ['code' => '', 'company_name' => ''],
            ],
        ]),
    ]);

    $session = new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    );

    $result = app(EPurchaseClient::class)->suppliers($session, 0, 10, 'acme');

    expect($result['recordsTotal'])->toBe(536)
        ->and($result['recordsFiltered'])->toBe(2)
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['code'])->toBe('SUP-00055');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/suppliers-master-list')
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request->hasHeader('Cookie', 'laravel_session=abc')
            && $request['start'] === '0'
            && $request['length'] === '10'
            && $request['search[value]'] === 'acme'
            && $request['_token'] === 'form-token-abc';
    });
});

it('throws session expired when suppliers returns 401', function () {
    Http::fake(['*/suppliers-master-list*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $session = new EPurchaseSession(
        jwt: 'stale-jwt',
        formToken: null,
        cookieHeader: '',
        expiresAt: time() + 600,
    );

    expect(fn () => app(EPurchaseClient::class)->suppliers($session, 0, 10))
        ->toThrow(EPurchaseSessionExpiredException::class);
});

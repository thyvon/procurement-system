<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\EPurchase\Services\EPurchaseClient;
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
    Http::fake(['*' => Http::response(epurchaseClientSuccessPayload())]);

    $result = app(EPurchaseClient::class)->login('3665', 'secret-company-password');

    expect($result->email)->toBe('vun.thy@mjqeducation.edu.kh')
        ->and($result->name)->toBe('Vun Thy')
        ->and($result->jwt)->toBe('company-session-jwt')
        ->and($result->userPhoto)->toBe('data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/default_user_access/login')
            && $request['employee_id'] === '3665'
            && $request['password'] === 'secret-company-password';
    });
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

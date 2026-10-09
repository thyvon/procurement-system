<?php

namespace Modules\EPurchase\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class EPurchaseClient
{
    /**
     * Upstream paths are part of the E-Purchase API contract. base_url is the
     * host only (no /api): login is under /api, master lists are at root.
     */
    private const LOGIN_PATH = '/api/default_user_access/login';

    private const REFRESH_PATH = '/api/default_user_access/refresh';

    private const MY_INFO_PATH = '/api/dashboard/getMyInfo';

    private const ITEMS_PATH = '/items-master-list';

    private const SUPPLIERS_PATH = '/suppliers-master-list';

    private const PR_LIST_PATH = '/api/pr/getRequestList';

    private const PR_DETAIL_PATH = '/pr-viewDetail/viewDetail';

    /**
     * Exchange company credentials for a company session and profile.
     *
     * The company JWT never leaves the server: callers receive only the
     * normalized name/email and optional profile photo needed for local
     * provisioning, plus the form token/cookies needed to cache a session.
     *
     * @throws InvalidCompanyCredentialsException when the company system rejects the credentials
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function login(string $employeeId, string $password): EPurchaseLoginResult
    {
        $response = $this->send(
            null,
            fn (PendingRequest $http) => $http->post(self::LOGIN_PATH, [
                'login_with_db' => false,
                'employee_id' => $employeeId,
                'password' => $password,
                'is_change_password' => 0,
            ]),
        );

        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('result', $body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        if ($body['result'] !== 'success') {
            throw new InvalidCompanyCredentialsException('Invalid credentials.');
        }

        $companyUser = $body['user'] ?? null;
        $email = is_array($companyUser) ? ($companyUser['email'] ?? null) : null;
        $name = is_array($companyUser) ? ($companyUser['name'] ?? null) : null;
        $position = is_array($companyUser) ? ($companyUser['real_position'] ?? null) : null;
        $jwt = $body['data'] ?? null;
        $userPhoto = $body['userPhoto'] ?? null;
        $formToken = $body['formToken'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($name) || $name === '' || ! is_string($jwt) || $jwt === '') {
            throw new EPurchaseUnavailableException('E-Purchase returned an incomplete login payload.');
        }

        return new EPurchaseLoginResult(
            jwt: $jwt,
            name: $name,
            email: $email,
            userPhoto: is_string($userPhoto) && $userPhoto !== '' ? $userPhoto : null,
            formToken: is_string($formToken) && $formToken !== '' ? $formToken : null,
            position: is_string($position) && trim($position) !== '' ? trim($position) : null,
            cookies: $this->cookiePairs($response),
        );
    }

    /**
     * Renew an upstream session by handing back the current JWT.
     *
     * The refresh endpoint is stateless: the access token itself is the
     * credential, so no extra refresh token needs to be stored. The renewed
     * session keeps the form token and cookies from the original login.
     *
     * @throws EPurchaseSessionExpiredException when the company system rejects the session
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function refresh(EPurchaseSession $session): EPurchaseSession
    {
        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $http->post(self::REFRESH_PATH, [
                'access_token' => $session->jwt,
            ]),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase session refresh failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();
        $jwt = is_array($body) ? ($body['access_token'] ?? null) : null;

        if (! is_string($jwt) || $jwt === '') {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        $expiresIn = is_array($body) ? ($body['expires_in'] ?? null) : null;

        return new EPurchaseSession(
            jwt: $jwt,
            formToken: $session->formToken,
            cookieHeader: $session->cookieHeader,
            expiresAt: time() + (is_numeric($expiresIn) && (int) $expiresIn > 0
                ? (int) $expiresIn
                : max(60, (int) config('epurchase.session_ttl', 7200))),
        );
    }

    /**
     * Fetch the signed-in company user's profile (signature image).
     *
     * The login payload does not carry the signature; the company dashboard
     * profile does. Callers decide whether a failure is fatal — login treats
     * it as best-effort, other flows may not.
     *
     * @throws EPurchaseSessionExpiredException when the session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function getMyInfo(EPurchaseSession $session): ?string
    {
        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $http->get(self::MY_INFO_PATH),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase profile request failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();

        if (! is_array($body) || ($body['result'] ?? null) !== 'success') {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        // The profile nests everything under data: {user, signature, campus, ...}.
        // A login-shaped fallback (data = JWT string) must yield null, not a TypeError.
        $data = $body['data'] ?? null;
        $signature = is_array($data) ? ($data['signature'] ?? null) : null;

        return is_string($signature) && trim($signature) !== '' ? trim($signature) : null;
    }

    /**
     * Fetch one page of company items (DataTables server protocol).
     *
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function items(EPurchaseSession $session, int $start, int $length, string $search = ''): array
    {
        $result = $this->dataTablePage(
            $session,
            self::ITEMS_PATH,
            $start,
            $length,
            $search,
            $this->itemsColumnsQuery(),
        );

        $result['data'] = array_values(array_filter(
            $result['data'],
            fn ($row): bool => is_array($row)
                && (trim((string) ($row['ItemCode'] ?? '')) !== ''
                    || trim((string) ($row['Description'] ?? '')) !== '')
        ));

        return $result;
    }

    /**
     * Fetch one page of company suppliers (DataTables server protocol).
     * suppliers-master-list ignores columns[] — draw/start/length/search is enough.
     *
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function suppliers(EPurchaseSession $session, int $start, int $length, string $search = ''): array
    {
        $result = $this->dataTablePage($session, self::SUPPLIERS_PATH, $start, $length, $search);

        $result['data'] = array_values(array_filter(
            $result['data'],
            fn ($row): bool => is_array($row)
                && (trim((string) ($row['code'] ?? '')) !== ''
                    || trim((string) ($row['company_name'] ?? '')) !== '')
        ));

        return $result;
    }

    /**
     * Fetch one page of company purchase requisitions (DataTables server protocol).
     * The PR list endpoint is a form-encoded POST instead of a GET.
     *
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function prs(EPurchaseSession $session, int $start, int $length, string $search = ''): array
    {
        return $this->dataTablePage(
            $session,
            self::PR_LIST_PATH,
            $start,
            $length,
            $search,
            $this->prColumnsQuery(),
            method: 'post',
            orderColumn: 6,
        );
    }

    /**
     * Fetch the line items of one purchase requisition.
     * Unlike the list endpoints this one answers with a bare JSON array.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function prDetail(EPurchaseSession $session, int $prId): array
    {
        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $http->get(self::PR_DETAIL_PATH, [
                'pr_id' => (string) $prId,
                'getPRDetailTable' => '1',
            ]),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase PR detail request failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        return array_values(array_filter($body, fn ($row): bool => is_array($row)));
    }

    /**
     * Authenticated request against an upstream DataTables endpoint.
     *
     * @param  array<string, string>  $extraQuery
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException
     * @throws EPurchaseUnavailableException
     */
    private function dataTablePage(
        EPurchaseSession $session,
        string $path,
        int $start,
        int $length,
        string $search,
        array $extraQuery = [],
        string $method = 'get',
        int $orderColumn = 11,
    ): array {
        $params = array_merge([
            'draw' => '1',
            'start' => (string) $start,
            'length' => (string) $length,
            'search[value]' => $search,
            'search[regex]' => 'false',
            'order[0][column]' => (string) $orderColumn,
            'order[0][dir]' => 'desc',
            'getTable' => '1',
            '_' => (string) (int) (microtime(true) * 1000),
            ...($session->formToken !== null && $session->formToken !== ''
                ? ['_token' => $session->formToken]
                : []),
        ], $extraQuery);

        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $method === 'post'
                ? $http->asForm()->post($path, $params)
                : $http->get($path, $params),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase list request failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        if (($body['success'] ?? null) === false) {
            throw new EPurchaseUnavailableException(
                is_string($body['message'] ?? null) && $body['message'] !== ''
                    ? $body['message']
                    : 'E-Purchase rejected the list request.'
            );
        }

        if (! array_key_exists('data', $body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        $rows = is_array($body['data']) ? $body['data'] : [];

        return [
            'recordsTotal' => (int) ($body['recordsTotal'] ?? 0),
            'recordsFiltered' => (int) ($body['recordsFiltered'] ?? count($rows)),
            'data' => array_values($rows),
        ];
    }

    /**
     * Shared HTTP bootstrap: base_url, timeout, retry policy, and optional
     * session headers (Bearer JWT + cookies). Connection failures map to
     * EPurchaseUnavailableException.
     *
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws EPurchaseUnavailableException
     */
    private function send(?EPurchaseSession $session, callable $send): Response
    {
        $baseUrl = config('epurchase.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new EPurchaseUnavailableException('E-Purchase is not configured.');
        }

        $http = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout((int) config('epurchase.timeout', 10))
            ->connectTimeout(5)
            ->retry(
                [100, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );

        if ($session !== null) {
            $http = $http->withHeaders([
                'Authorization' => 'Bearer '.$session->jwt,
                'X-Requested-With' => 'XMLHttpRequest',
                ...($session->cookieHeader !== '' ? ['Cookie' => $session->cookieHeader] : []),
            ]);
        }

        try {
            return $send($http);
        } catch (ConnectionException $exception) {
            throw new EPurchaseUnavailableException('E-Purchase is unreachable.', 0, $exception);
        }
    }

    /**
     * @return array<int, string> name=value cookie pairs for the Cookie request header
     */
    private function cookiePairs(Response $response): array
    {
        $pairs = [];

        foreach ($response->cookies() as $cookie) {
            $pairs[] = $cookie->getName().'='.$cookie->getValue();
        }

        if ($pairs === []) {
            $setCookie = $response->header('Set-Cookie');
            if (is_string($setCookie) && $setCookie !== '') {
                foreach (explode("\n", $setCookie) as $line) {
                    $first = trim(explode(';', $line, 2)[0]);
                    if ($first !== '') {
                        $pairs[] = $first;
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, string>
     */
    private function itemsColumnsQuery(): array
    {
        return $this->columnsQuery([
            ['data' => 'image', 'name' => 'image', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'ItemCode', 'name' => 'ItemCode', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'Description', 'name' => 'Description', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'LongDescription', 'name' => 'LongDescription', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'BaseItemUnit', 'name' => 'BaseItemUnit', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'category', 'name' => 'category', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'sub_category', 'name' => 'sub_category', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'is_manage_price', 'name' => 'is_manage_price', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'estimate_price', 'name' => 'estimate_price', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'avg_price_3_months', 'name' => 'avg_price_3_months', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'remark', 'name' => 'remark', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'show_created_at', 'name' => 'created_at', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'created_by', 'name' => 'created_by', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'show_updated_at', 'name' => 'updated_at', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'updated_by_name', 'name' => 'updated_by_name', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'Status', 'name' => 'Status', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'id', 'name' => 'Action', 'searchable' => '', 'orderable' => ''],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function prColumnsQuery(): array
    {
        return $this->columnsQuery([
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'no', 'name' => 'no', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'RefNum', 'name' => 'RefNum', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'Purpose', 'name' => 'Purpose', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'amount', 'name' => 'amount', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'requester', 'name' => 'requester', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'created_at', 'name' => 'created_at', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'status', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'purchase_status', 'name' => 'purchase_status', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => '', 'name' => 'Action', 'searchable' => 'false', 'orderable' => 'false'],
        ]);
    }

    /**
     * @param  array<int, array{data: string, name: string, searchable: string, orderable: string}>  $cols
     * @return array<string, string>
     */
    private function columnsQuery(array $cols): array
    {
        $query = [];
        foreach ($cols as $i => $col) {
            $query["columns[$i][data]"] = $col['data'];
            $query["columns[$i][name]"] = $col['name'];
            $query["columns[$i][searchable]"] = $col['searchable'];
            $query["columns[$i][orderable]"] = $col['orderable'];
            $query["columns[$i][search][value]"] = '';
            $query["columns[$i][search][regex]"] = 'false';
        }

        return $query;
    }
}

<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Telegram\Http\Requests\IndexTelegramMessagesRequest;
use Modules\Telegram\Http\Requests\StoreTelegramMessageRequest;
use Modules\Telegram\Http\Resources\TelegramMessageResource;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;
use Modules\Telegram\Repositories\TelegramMessageRepositoryInterface;
use Modules\Telegram\Services\TelegramGateway;

class TelegramMessageController extends Controller
{
    public function __construct(
        private readonly TelegramMessageRepositoryInterface $repo,
        private readonly TelegramGateway $gateway,
    ) {}

    public function index(IndexTelegramMessagesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TelegramMessage::class);

        $paginator = $this->repo->forContact(
            $request->validated('telegram_contact_id'),
            (int) $request->integer('per_page', 50),
        );

        return ApiResponse::success(TelegramMessageResource::collection($paginator->getCollection())->resolve($request));
    }

    public function store(StoreTelegramMessageRequest $request): JsonResponse
    {
        $this->authorize('create', TelegramMessage::class);

        $contact = TelegramContact::query()
            ->findOrFail($request->validated('telegram_contact_id'));

        $message = $this->gateway->sendToContact(
            contact: $contact,
            body: $request->validated('body'),
            idempotencyKey: $request->validated('idempotency_key'),
            user: $request->user(),
        );

        // The job may already have run (sync queue / fast worker) — re-read so
        // the response reflects the real status instead of the pre-send row.
        $message->refresh();

        return ApiResponse::success(
            new TelegramMessageResource($message),
            $message->wasRecentlyCreated ? 201 : 200,
        );
    }
}

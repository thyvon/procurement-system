<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Products\Http\Requests\StoreVariationTemplateRequest;
use Modules\Products\Http\Resources\ProductResource;
use Modules\Products\Http\Resources\VariationTemplateResource;
use Modules\Products\Models\Product;
use Modules\Products\Models\VariationTemplate;
use Modules\Products\Repositories\ProductRepositoryInterface;
use Modules\Products\Repositories\VariationTemplateRepositoryInterface;
use Modules\Products\Services\VariationService;

class VariationController extends Controller
{
    public function __construct(
        private readonly VariationTemplateRepositoryInterface $templates,
        private readonly ProductRepositoryInterface $products,
        private readonly VariationService $variation,
    ) {}

    public function templateIndex(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VariationTemplate::class);

        return VariationTemplateResource::collection($this->templates->paginate(100));
    }

    public function templateStore(StoreVariationTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', VariationTemplate::class);

        /** @var User $user */
        $user = $request->user();

        [$data, $options] = $this->splitPayload($request->validated());

        $template = DB::transaction(function () use ($user, $data, $options) {
            $template = $this->templates->create([
                ...$data,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
            $this->syncOptions($template, $options);

            return $template;
        });

        return ApiResponse::success(new VariationTemplateResource($template->load('options')), 201);
    }

    public function merge(Request $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $parent = $this->variation->merge(
                $user,
                (string) $request->input('parentId'),
                (array) $request->input('templateIds', []),
                (array) $request->input('assignments', []),
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(422, $e->getMessage(), 'InvalidMergeRequest');
        }

        return ApiResponse::success(new ProductResource($parent));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function splitPayload(array $validated): array
    {
        $options = $validated['options'] ?? [];
        unset($validated['options']);

        return [$validated, $options];
    }

    private function syncOptions(VariationTemplate $template, array $options): void
    {
        foreach ($options as $index => $row) {
            $template->options()->create([
                'value' => $row['value'],
                'sort_order' => $row['sort_order'] ?? $index,
            ]);
        }
    }
}

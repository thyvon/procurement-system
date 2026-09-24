<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Products\Http\Requests\StoreMergeVariationRequest;
use Modules\Products\Http\Requests\StoreVariationTemplateRequest;
use Modules\Products\Http\Requests\UpdateVariationTemplateRequest;
use Modules\Products\Http\Resources\ProductResource;
use Modules\Products\Http\Resources\VariationTemplateResource;
use Modules\Products\Models\Product;
use Modules\Products\Models\VariationTemplate;
use Modules\Products\Models\VariationTemplateOption;
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

    public function templateShow(VariationTemplate $variationTemplate): VariationTemplateResource
    {
        $this->authorize('view', $variationTemplate);

        return new VariationTemplateResource($variationTemplate->load('options'));
    }

    public function templateUpdate(UpdateVariationTemplateRequest $request, VariationTemplate $variationTemplate): VariationTemplateResource
    {
        $this->authorize('update', $variationTemplate);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();
        $hasOptions = array_key_exists('options', $validated);
        [$data, $options] = $this->splitPayload($validated);

        DB::transaction(function () use ($user, $variationTemplate, $data, $options, $hasOptions) {
            $data['updated_by'] = $user->getKey();
            $updated = $this->templates->update($variationTemplate, $data);

            if ($hasOptions) {
                $this->syncOptions($updated, $options);
            }
        });

        return new VariationTemplateResource($variationTemplate->load('options'));
    }

    public function templateDestroy(VariationTemplate $variationTemplate): JsonResponse
    {
        $this->authorize('delete', $variationTemplate);

        if ($this->templateIsInUse($variationTemplate)) {
            abort(409, 'Cannot delete a variation template that is assigned to products or used by variants.');
        }

        DB::transaction(function () use ($variationTemplate) {
            $variationTemplate->options()->delete();
            $this->templates->delete($variationTemplate);
        });

        return ApiResponse::success(['deleted' => true]);
    }

    public function merge(StoreMergeVariationRequest $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        /** @var User $user */
        $user = $request->user();

        $parent = $this->variation->merge(
            $user,
            (string) $request->validated('parentId'),
            (array) $request->validated('templateIds'),
            (array) $request->validated('assignments'),
        );

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

    /**
     * Upsert-by-id option rows and hard-delete rows no longer present,
     * mirroring UomController::syncSubUnits.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(VariationTemplate $template, array $options): void
    {
        $keepIds = [];

        foreach ($options as $index => $row) {
            $option = ! empty($row['id'])
                ? $template->options()->findOrFail($row['id'])
                : new VariationTemplateOption;

            $option->forceFill([
                'variation_template_id' => $template->getKey(),
                'value' => $row['value'],
                'sort_order' => $row['sort_order'] ?? $index,
            ]);
            $option->save();
            $keepIds[] = $option->getKey();
        }

        $template->options()->whereNotIn('id', $keepIds)->delete();
    }

    private function templateIsInUse(VariationTemplate $template): bool
    {
        $assignedToProduct = DB::table('product_variation_template')
            ->where('variation_template_id', $template->getKey())
            ->exists();

        if ($assignedToProduct) {
            return true;
        }

        $optionIds = $template->options()->pluck('id');
        if ($optionIds->isEmpty()) {
            return false;
        }

        return DB::table('product_variants')
            ->where(function ($query) use ($optionIds) {
                foreach ($optionIds as $optionId) {
                    $query->orWhere('option_values', 'like', '%'.$optionId.'%');
                }
            })
            ->exists();
    }
}

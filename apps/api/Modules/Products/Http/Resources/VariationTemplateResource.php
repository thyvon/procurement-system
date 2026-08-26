<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Products\Models\VariationTemplate;

/**
 * @mixin VariationTemplate
 */
class VariationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isActive' => $this->is_active,
            'options' => $this->whenLoaded(
                'options',
                fn () => $this->options->map(fn ($o) => [
                    'id' => $o->id,
                    'value' => $o->value,
                    'sortOrder' => $o->sort_order,
                ]),
            ),
        ];
    }
}

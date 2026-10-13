<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SettingResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'settings';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'description' => $this->description,
        ];
    }

    /** JSON:API forbids an attribute named `type`; expose the value cast type as `value_type`. */
    protected function jsonApiAttributes(Request $request): array
    {
        $data = $this->data($request);
        $data['value_type'] = $data['type'] ?? null;

        return Arr::except($data, ['id', 'type']);
    }
}

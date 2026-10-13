<?php

namespace App\Http\Resources;

use App\Support\JsonApi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Base API resource that renders either the default Laravel-resource shape or, when the
 * client opted into JSON:API ({@see JsonApi::requested()}), a spec-compliant resource
 * object ({@link https://jsonapi.org/format/#document-resource-objects}):
 *
 *     { "type": "pages", "id": "1", "attributes": { ... } }
 *
 * Subclasses provide the default attribute map via {@see data()} and the JSON:API resource
 * type via {@see resourceType()}. The identifier defaults to the model key; override
 * {@see resourceId()} for non-Eloquent resources. Because JSON:API forbids `id` and `type`
 * as field names, {@see jsonApiAttributes()} strips `id` by default — resources whose default
 * payload contains a `type` field must override it to rename that field.
 */
abstract class JsonApiResource extends JsonResource
{
    /** The JSON:API resource type (e.g. "pages"). */
    abstract protected function resourceType(): string;

    /** The default flat representation, returned verbatim when JSON:API is not requested. */
    abstract protected function data(Request $request): array;

    public function toArray(Request $request): array
    {
        if (! JsonApi::requested($request)) {
            return $this->data($request);
        }

        return [
            'type' => $this->resourceType(),
            'id' => $this->resourceId(),
            'attributes' => $this->jsonApiAttributes($request),
        ];
    }

    /** The JSON:API resource identifier as a string. */
    protected function resourceId(): string
    {
        $resource = $this->resource;

        if (is_object($resource) && method_exists($resource, 'getKey')) {
            return (string) $resource->getKey();
        }

        return (string) ($resource->id ?? '');
    }

    /**
     * The JSON:API `attributes` object: the default payload without the reserved `id` field.
     * Override to also rename a `type` field, which JSON:API disallows as an attribute.
     */
    protected function jsonApiAttributes(Request $request): array
    {
        return Arr::except($this->data($request), ['id']);
    }
}

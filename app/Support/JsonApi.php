<?php

namespace App\Support;

use App\Http\Middleware\NegotiateJsonApi;
use Illuminate\Http\Request;

/**
 * Helpers for the opt-in JSON:API (jsonapi.org) representation of the /api/v1 endpoints.
 *
 * Clients opt in per request with `Accept: application/vnd.api+json`. When they do,
 * {@see NegotiateJsonApi} records the intent on the request (the
 * Accept header itself is overwritten later by ForceJsonResponse, so we cannot rely on
 * it downstream) and resources/controllers switch to the JSON:API document structure.
 * Without the header the default Laravel-resource format is returned.
 */
class JsonApi
{
    /** The JSON:API media type. */
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /** The implemented JSON:API specification version. */
    public const VERSION = '1.1';

    /** Request attribute flag set by the negotiation middleware. */
    public const ATTRIBUTE = 'jsonapi';

    /** Whether the client requested the JSON:API representation for this request. */
    public static function requested(Request $request): bool
    {
        return (bool) $request->attributes->get(self::ATTRIBUTE, false);
    }

    /** Whether the given Accept header string opts into JSON:API. */
    public static function acceptsJsonApi(?string $accept): bool
    {
        return $accept !== null && str_contains($accept, self::MEDIA_TYPE);
    }
}

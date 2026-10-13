<?php

namespace App\Http\Middleware;

use App\Support\JsonApi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in JSON:API (jsonapi.org) content negotiation for the /api/v1 endpoints.
 *
 * This middleware must run before {@see ForceJsonResponse} (which overwrites the Accept
 * header): it records whether the client asked for `application/vnd.api+json` on the request
 * so resources can switch to the JSON:API resource-object shape. On the way out it applies
 * the cross-cutting document rules the resources cannot: the `application/vnd.api+json`
 * response content type, the top-level `jsonapi` member, relocating any non-standard
 * top-level members into `meta`, and rewriting error payloads into JSON:API error objects.
 *
 * Requests that do not opt in are passed through completely unchanged.
 */
class NegotiateJsonApi
{
    /** Top-level members allowed in a JSON:API success document. */
    private const SUCCESS_MEMBERS = ['data', 'meta', 'links', 'included', 'jsonapi'];

    public function handle(Request $request, Closure $next): Response
    {
        $wants = JsonApi::acceptsJsonApi($request->header('Accept'));
        $request->attributes->set(JsonApi::ATTRIBUTE, $wants);

        $response = $next($request);

        if (! $wants || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (is_array($payload)) {
            $response->setData(
                $response->isSuccessful()
                    ? $this->successDocument($payload)
                    : $this->errorDocument($payload, $response->getStatusCode())
            );
        }

        $response->headers->set('Content-Type', JsonApi::MEDIA_TYPE);

        return $response;
    }

    /**
     * Normalise a success payload: keep the standard members, fold everything else into
     * `meta`, and prepend the top-level `jsonapi` member.
     */
    private function successDocument(array $payload): array
    {
        $document = [];
        $meta = $payload['meta'] ?? [];

        foreach ($payload as $key => $value) {
            if ($key === 'meta' || $key === 'jsonapi') {
                continue;
            }

            if (in_array($key, self::SUCCESS_MEMBERS, true)) {
                $document[$key] = $key === 'data' ? $this->normaliseData($value) : $value;
            } else {
                $meta[$key] = $value;
            }
        }

        if ($meta !== []) {
            $document['meta'] = $meta;
        }

        return ['jsonapi' => ['version' => JsonApi::VERSION]] + $document;
    }

    /**
     * A JSON:API collection's `data` must be an array (list). Some controllers return a
     * keyed map of resources (e.g. themes keyed by slug); re-index those to a list. A single
     * resource object (identified by its `type` member) is left untouched.
     */
    private function normaliseData(mixed $data): mixed
    {
        if (is_array($data) && $data !== [] && ! array_is_list($data) && ! array_key_exists('type', $data)) {
            return array_values($data);
        }

        return $data;
    }

    /**
     * Convert a Laravel error payload ({message, errors?}) into a JSON:API error document.
     */
    private function errorDocument(array $payload, int $status): array
    {
        $statusString = (string) $status;
        $title = $payload['message'] ?? (Response::$statusTexts[$status] ?? 'Error');
        $errors = [];

        $validation = $payload['errors'] ?? null;

        if (is_array($validation) && $validation !== []) {
            foreach ($validation as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $error = [
                        'status' => $statusString,
                        'title' => $title,
                        'detail' => (string) $message,
                    ];

                    // Field-keyed validation errors carry an attribute source pointer.
                    if (is_string($field)) {
                        $error['source'] = ['pointer' => '/data/attributes/'.$field];
                    }

                    $errors[] = $error;
                }
            }
        } else {
            $errors[] = [
                'status' => $statusString,
                'title' => Response::$statusTexts[$status] ?? 'Error',
                'detail' => is_string($title) ? $title : 'Error',
            ];
        }

        $document = ['jsonapi' => ['version' => JsonApi::VERSION], 'errors' => $errors];

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $document['meta'] = $payload['meta'];
        }

        return $document;
    }
}

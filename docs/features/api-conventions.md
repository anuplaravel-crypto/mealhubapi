# API Conventions

The wire contract every MealHubApi endpoint honours, and the one MealHubReact codes
against. Implemented by `App\Http\Traits\ApiResponse` (responses controllers build)
and the `withExceptions` handler in `bootstrap/app.php` (responses the framework
throws). Controllers never hand-roll `response()->json()`.

For the *why* behind the layering these responses come out of, see
[../conventions.md](../conventions.md).

## The envelope

Every JSON body carries a boolean `success`. Nothing else is guaranteed across both
shapes, so a client can branch on that one key.

**Success** — `successResponse($data, $message = null, $status = 200)`

```json
{
  "success": true,
  "data": { "id": 7, "email": "jane@example.com" },
  "message": "Fetched successfully."
}
```

`data` is always present (it may be `null`); `message` is omitted entirely when none
was given.

**Error** — `errorResponse($message, $status, $errors = null)`

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

`errors` is omitted unless the failure maps onto specific input fields.

!!! warning "The status argument is required"
    `errorResponse()` takes its status as the **second** positional argument and has
    no default. It previously defaulted to `422`, which meant a forgotten argument
    silently reported a 403 or a 404 as a validation failure. Passing the status is
    now mandatory at the call site.

**Empty** — `noContentResponse()`

Returns `204` with a zero-length body — no envelope, because there is nothing to
wrap. Used by deletes and other command-style endpoints. Clients must not attempt to
parse the body of a 204.

## Paginated lists

`paginatedResponse($paginated, $message = null)` accepts either an
`Illuminate\Pagination\LengthAwarePaginator` or a `ResourceCollection` wrapping one.

```json
{
  "success": true,
  "data": [ { "id": 1 }, { "id": 2 } ],
  "meta": {
    "current_page": 1,
    "per_page": 2,
    "last_page": 3,
    "total": 5,
    "from": 1,
    "to": 2,
    "links": {
      "first": "https://api.example.test/api/v1/notifications?page=1",
      "last":  "https://api.example.test/api/v1/notifications?page=3",
      "prev":  null,
      "next":  "https://api.example.test/api/v1/notifications?page=2"
    }
  }
}
```

**Why this exists.** Handing a paginator straight to `successResponse()` nests
Laravel's own `{data, links, meta}` under `data`, so the client reads `data.data[0]`
for a list but `data.id` for a single record. `paginatedResponse()` lifts the rows to
`data` and the paginator state to a sibling `meta`, so `data` means the same thing on
every endpoint. Laravel's separate top-level `links` block is folded into `meta.links`
for the same reason — one place to look.

Passing a non-paginated collection is a programming error and raises
`InvalidArgumentException` (a 500), not a silently malformed list.

## Error statuses

Framework-thrown errors are reshaped into the error envelope for every `api/*` route,
so these are consistent whether a controller, a Form Request, or the router produced
them.

| Status | When | Body |
| --- | --- | --- |
| `401` | No token, or an invalid one, on an `auth:sanctum` route | `message: "Unauthenticated."` |
| `403` | A policy or gate denied the request | the policy's message, else `"This action is unauthorized."` |
| `404` | Unknown route, or a route-model binding that matched nothing | `message: "Resource not found."` |
| `422` | Form Request validation failed | `errors` keyed by field |
| `429` | A `throttle:` limit was exceeded | adds `retry_after` (seconds) |
| `500` | Anything unhandled | `message: "Server Error"` |

### Notes on individual statuses

- **404 never names a model.** Laravel's default for a failed binding is
  `No query results for model [App\Models\User] 5`, which leaks an internal class
  name and the queried id. Every `api/*` 404 is replaced with `Resource not found.`
- **429 repeats `Retry-After` in the body.** The header is still set; the body copy
  exists so a browser client can render a countdown without reading response headers
  (which CORS may not expose).
- **500 never leaks a stack trace**, in either debug mode. The envelope only ever
  forwards `message` and `errors`, so the `exception` / `file` / `line` / `trace` keys
  of a debug payload are dropped before the response leaves the app. `APP_DEBUG=true`
  still gives you the full trace in the log and in `php artisan pail`.

## Raising errors from a service

Services do not build responses and must not call `abort()` (an HTTP concern) or
throw `ValidationException` for a failure that isn't a validation failure. They throw
`App\Exceptions\DomainException`, which carries its own status:

```php
throw new DomainException('This order can no longer be cancelled.', 409);

// …or with field-level detail, when the failure does map onto input:
throw new DomainException('Coupon rejected.', 422, [
    'coupon' => ['This coupon has expired.'],
]);
```

The handler renders it through the error envelope with the status given. The default
status is `400`.

## Tests

`tests/Feature/Api/ResponseEnvelopeTest.php` and
`tests/Feature/Api/ExceptionEnvelopeTest.php` pin every shape on this page, including
the 404 class-name leak and the 500 stack-trace leak. Treat them as the executable
version of this document — change them only alongside a deliberate contract change,
since MealHubReact depends on these shapes.

<?php

namespace App\Http\Requests\Admin;

use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the three admin user lists.
 *
 * Net-new — the reference app rendered every account into a client-side
 * DataTable, which stops being an option the moment the list is a paged JSON
 * response. One Request serves all three lists because the parameters are the
 * same for all three; the *role* is not among them, and never will be. It is
 * fixed by the controller, so no query string can widen a customer list into
 * everybody.
 *
 * **`sort` is the rule that matters most.** It reaches
 * {@see UserRepository::paginateByRole()} as a column name in `orderBy()`, so
 * the `Rule::in()` below is not a convenience for the client — it is the only
 * thing between a query parameter and an identifier in SQL.
 */
class ListUsersRequest extends FormRequest
{
    /**
     * Sort columns a caller may name.
     *
     * A whitelist rather than a "is it a real column" check: `password`,
     * `otp` and `remember_token` are real columns too, and ordering by one
     * leaks its ordering.
     *
     * @var list<string>
     */
    public const SORTABLE = ['created_at', 'updated_at', 'firstName', 'lastName', 'email', 'status'];

    /**
     * Authorization is the route's job here. `auth:sanctum` plus `role:admin`
     * is the whole question for a *list* — it takes no id, so there is no
     * target row for this class to check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],

            // Sent as `1`/`0`/`true`/`false` from a query string; absent means
            // "both", which is why there is no default and no `required`.
            'status' => ['sometimes', 'nullable', 'boolean'],

            'sort' => ['sometimes', 'nullable', 'string', Rule::in(self::SORTABLE)],
            'direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],

            // Capped at 100 rather than left open: an unbounded page size turns
            // a paginated endpoint back into the full-table read this replaced.
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * The validated parameters, shaped as the service's filter array.
     *
     * Two things happen here that `validated()` alone does not do. Empty
     * parameters are dropped, so `?search=` reads as absent rather than as a
     * search for the empty string and `?per_page=` falls back to
     * {@see UserManagementService::DEFAULT_PER_PAGE} rather than to zero. And
     * `status` and `per_page` are cast, because a query string carries `"0"`
     * and `"20"` as strings — the repository should compare a boolean to a
     * boolean rather than lean on the database to guess.
     *
     * @return array{search?: string, status?: bool, sort?: string, direction?: string, per_page?: int}
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        $filters = array_filter($validated, static fn (mixed $value): bool => $value !== null && $value !== '');

        if (array_key_exists('status', $filters)) {
            $filters['status'] = $this->boolean('status');
        }

        if (array_key_exists('per_page', $filters)) {
            $filters['per_page'] = (int) $filters['per_page'];
        }

        return $filters;
    }
}

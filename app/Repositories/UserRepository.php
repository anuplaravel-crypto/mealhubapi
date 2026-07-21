<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    /**
     * Resolve a user by email within a single role.
     *
     * The four roles share the `users` table, so every credential lookup must
     * be scoped by role — otherwise a customer's email would resolve at the
     * admin endpoints. Callers pass an already-normalized (lowercased) email.
     */
    public function findByEmailAndRole(string $email, string $role): ?User
    {
        return $this->query()
            ->where('role', $role)
            ->where('email', $email)
            ->first();
    }

    /**
     * One page of a single role's accounts, for the admin management lists.
     *
     * The role is a *scope*, not a filter the caller may drop: the four roles
     * share the `users` table, so a listing that did not pin it would hand an
     * admin browsing customers a page of riders — and, worse, would let the
     * ids on that page resolve at {@see self::findByRoleOrFail()}.
     *
     * `$sort` reaches `orderBy()` as a column name, so it must arrive already
     * whitelisted; `Admin\ListUsersRequest` is what does that, and this method
     * must never be called with an unvalidated string. The tie-break on `id`
     * keeps paging stable when many rows share a sort value.
     *
     * @param  array{search?: string|null, status?: bool|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateByRole(string $role, array $filters, int $perPage): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        return $this->query()
            ->where('role', $role)
            ->with($this->managementRelationsFor($role))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when(filled($search), fn (Builder $query) => $query->where(
                fn (Builder $group) => $group
                    ->orWhere('firstName', 'like', $this->likePattern($search))
                    ->orWhere('lastName', 'like', $this->likePattern($search))
                    ->orWhere('email', 'like', $this->likePattern($search))
                    ->orWhere('mobile', 'like', $this->likePattern($search))
            ))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * A single account, scoped to one role — the lookup every admin endpoint
     * that takes an id from the URL goes through.
     *
     * **This is what stands in for a Policy on those routes.** `role:admin`
     * proves the caller; pinning the role here proves the *target*, so an id
     * naming a rider cannot resolve under `admin/customers`, and an id naming
     * an admin cannot resolve anywhere — a wrong-collection id is a 404 rather
     * than a row an ability would then have to refuse. A Policy could only
     * re-check the same column after a wider query had already found the row.
     *
     * @throws ModelNotFoundException<User>
     */
    public function findByRoleOrFail(int|string $id, string $role): User
    {
        return $this->query()
            ->where('role', $role)
            ->with($this->managementRelationsFor($role))
            ->findOrFail($id);
    }

    /**
     * What the admin list and profile reads eager-load, per role.
     *
     * Every role's row renders its location; only a rider's carries a vehicle.
     * Deciding it here rather than in the service keeps the query count fixed
     * without a Resource ever touching a lazy relation — which is the whole
     * N+1 the admin lists would otherwise have.
     *
     * @return list<string>
     */
    private function managementRelationsFor(string $role): array
    {
        return $role === 'rider'
            ? ['country', 'county', 'city', 'vehicles']
            : ['country', 'county', 'city'];
    }

    /**
     * Wrap a search term for `LIKE`, escaping the wildcards it may contain.
     *
     * Without this a term of `%` matches every row and `_` matches any single
     * character — a search box that silently behaves as a pattern language.
     */
    private function likePattern(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }

    /**
     * Account tallies for the given roles, keyed by role — one row per role
     * that has any accounts, carrying `total` and `active`.
     *
     * **One query for every role, not one per role.** A dashboard that counted
     * each tile separately would issue three round trips to answer what
     * `GROUP BY role` answers in one, and that count would grow with every role
     * added. `SUM(CASE WHEN ...)` rather than a second grouped query because
     * `status` is the only split, and MySQL and SQLite agree on the expression.
     *
     * `toBase()` because these are aggregates, not accounts: hydrating a `User`
     * per row would produce three model instances whose only real attribute is
     * `role`, and a caller could mistake one for an account.
     *
     * A role with no accounts is simply absent — filling the gap with a zero is
     * presentation, and belongs to the caller that knows which tiles it renders.
     * See {@see DashboardService::userCounts()}.
     *
     * @param  list<string>  $roles
     * @return SupportCollection<string, object{role: string, total: int, active: int}>
     */
    public function countsByRole(array $roles): SupportCollection
    {
        return $this->query()
            ->toBase()
            ->whereIn('role', $roles)
            ->groupBy('role')
            ->selectRaw('role, COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active')
            ->get()
            ->keyBy('role');
    }

    /**
     * Every admin account, the audience for the operational notifications a
     * registration or a document upload raises.
     *
     * Returned as a collection for `Notification::send()`, which fans one
     * notification out across all of them — there is no "the admin" row.
     *
     * @return Collection<int, User>
     */
    public function admins(): Collection
    {
        return $this->query()->where('role', 'admin')->get();
    }

    /**
     * Reload a user with the country/county/city rows their ids point at.
     *
     * Eager loading here rather than letting a Resource touch `$user->country`
     * is what keeps the profile endpoints at a fixed query count — and `load()`
     * re-queries every call, so a profile update that moved the user to another
     * city cannot answer with the previous city still attached.
     */
    public function withLocation(User $user): User
    {
        return $user->load(['country', 'county', 'city']);
    }

    /**
     * Issue a Sanctum personal access token and return its plaintext value.
     *
     * The plaintext is only ever available here, at issuance — it is never
     * readable again from the stored hash.
     */
    public function issueToken(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    /**
     * Revoke the token that authenticated the current request.
     */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Revoke every token the user holds — used when a password is reset by
     * someone who was locked out, so any session an attacker holds dies too.
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Revoke every token except the one authenticating this request — used
     * when a signed-in user changes their own password, which should sign
     * out their other devices without signing out the one they are using.
     */
    public function revokeOtherTokens(User $user): void
    {
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->getKey())
            ->delete();
    }
}

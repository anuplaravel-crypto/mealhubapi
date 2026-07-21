<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Cms\BaseCmsController;
use App\Http\Controllers\Api\V1\Auth\BaseAuthController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Traits\ApiResponse;
use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;

/**
 * The shared admin surface over one role's accounts — list, read, activate or
 * deactivate.
 *
 * The same technique as {@see BaseAuthController} and {@see BaseCmsController}:
 * every action's body lives here and a concrete controller supplies only the
 * role it manages and the noun for its messages. Three subclasses exist rather
 * than one controller taking the role from the path, because the role must not
 * be something a request can choose — fixing it in the class is what makes it
 * unforgeable.
 *
 * **Ids arrive as plain integers rather than through route-model binding**, for
 * the reason `BaseCmsController` records: binding resolves from the *concrete
 * method's* type hint, so a shared action here could not declare one and each
 * subclass would have to restate every method to name `User`. It also happens
 * to be the safer shape — binding would resolve any user row and leave the role
 * check to be remembered afterwards, whereas
 * {@see UserRepository::findByRoleOrFail()} never finds the wrong row at all.
 *
 * **No Policy appears in this namespace, despite the ids.** The rule in
 * CLAUDE.md is that a Policy verifies ownership wherever an id arrives from the
 * URL, and there is no ownership here — an admin does not *own* a customer. The
 * two things authorization has to establish are that the caller is an admin,
 * which `role:admin` proves, and that the id names an account of this
 * controller's role, which the scoped lookup proves by not finding anything
 * else. An ability could only re-read the same column after a wider query. The
 * one route in this phase that *does* carry a Policy is the vehicle photo
 * stream, which binds a `User` and hands a private file to the caller.
 */
abstract class BaseUserManagementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected readonly UserManagementService $userManagement,
    ) {}

    /**
     * The role this controller manages — fixed by the class, never read from
     * the request.
     */
    abstract protected function role(): string;

    /**
     * How an account of this role is named in response messages, singular and
     * capitalised: "Customer", "Restaurant", "Rider".
     */
    abstract protected function label(): string;

    /**
     * One page of this role's accounts.
     *
     * Paginated, unlike the CMS lists: these tables grow without bound and have
     * no editable order to preserve, so the reference app's render-everything
     * DataTable is the thing being replaced rather than reproduced.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            AdminUserResource::collection(
                $this->userManagement->list($this->role(), $request->filters()),
            ),
        );
    }

    /**
     * A single account of this role, with the material an admin reviews it on.
     *
     * An id belonging to another role — or to another admin — is a 404 here,
     * not a 403: the account genuinely is not in the collection being browsed,
     * and a 403 would confirm that some *other* collection holds it.
     */
    public function show(int $id): JsonResponse
    {
        return $this->successResponse(
            new AdminUserResource($this->userManagement->show($this->role(), $id)),
        );
    }

    /**
     * Flip the account's activation gate.
     *
     * A toggle rather than a set: the caller sends no target state, so a
     * replayed request cannot reactivate an account an admin had just
     * suspended. The updated account comes back so the client reads the new
     * state rather than assuming its own guess landed.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $result = $this->userManagement->toggleStatus($this->role(), $id);

        return $this->successResponse(
            new AdminUserResource($result['user']),
            $this->label().($result['activated'] ? ' account activated.' : ' account deactivated.'),
        );
    }
}

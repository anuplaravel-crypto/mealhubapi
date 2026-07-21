<?php

namespace App\Http\Controllers\Api\V1\Admin\Cms;

use App\Http\Controllers\Api\V1\Auth\BaseAuthController;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\Cms\BaseCmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\UploadedFile;

/**
 * The shared admin surface over a reorderable CMS collection — list, create,
 * edit, toggle, remove.
 *
 * The same technique as {@see BaseAuthController}: the body of each action
 * lives here, and a concrete controller supplies the service, the Resource
 * and — for the two actions that take a payload — its own type-hinted Form
 * Request, since that hint is what selects the rules.
 *
 * **Ids arrive as plain integers rather than through route-model binding.**
 * Binding resolves the model from the *controller method's* type hint, so a
 * shared `destroy()` here could not name one; every concrete controller would
 * have to re-declare `toggle()` and `destroy()` purely to restate their model,
 * which is the duplication this base exists to remove. `findOrFail()` in the
 * repository is the single not-found path instead, and the `NotFoundHttpException`
 * callback in `bootstrap/app.php` turns it into the project's 404 envelope
 * without leaking a model class name.
 *
 * **No Policy appears in this namespace, despite the ids.** CMS records have no
 * owner — being an admin is the entire authorization question, exactly as it is
 * for newsletter subscribers. The rule in CLAUDE.md is that an id from the URL
 * needs a Policy wherever there is ownership to verify; here `role:admin` on the
 * route is what there is to check.
 */
abstract class BaseCmsController extends Controller
{
    use ApiResponse;

    /**
     * The service this controller drives.
     *
     * @return BaseCmsService<Model>
     */
    abstract protected function service(): BaseCmsService;

    /**
     * The Resource each row is shaped by — an admin variant, because the public
     * ones deliberately hide the editorial fields.
     *
     * @return class-string<JsonResource>
     */
    abstract protected function resource(): string;

    /**
     * How this collection's rows are named in response messages, singular and
     * capitalised: "Testimonial", "Meal category".
     */
    abstract protected function label(): string;

    /**
     * Every row in display order.
     *
     * Deliberately not paginated, unlike the newsletter list. These tables hold
     * an editorial set of a few dozen rows whose order is itself editable, and
     * an admin cannot sensibly reorder a list they can only see one page of.
     * The complete set is also what the public payload returns, so the two
     * reads stay comparable.
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->resource()::collection($this->service()->listAll()),
        );
    }

    /**
     * Add a row. Concrete controllers call this from their own `store()` so
     * each can type-hint the Form Request whose rules apply.
     *
     * @param  array<string, mixed>  $data
     */
    protected function completeStore(array $data, ?UploadedFile $image = null): JsonResponse
    {
        return $this->successResponse(
            new ($this->resource())($this->service()->create($data, $image)),
            $this->label().' created.',
            201,
        );
    }

    /**
     * Edit a row.
     *
     * @param  array<string, mixed>  $data
     */
    protected function completeUpdate(int $id, array $data, ?UploadedFile $image = null): JsonResponse
    {
        return $this->successResponse(
            new ($this->resource())($this->service()->update($id, $data, $image)),
            $this->label().' updated.',
        );
    }

    /**
     * Show or hide a row on the public page.
     *
     * A toggle rather than a set: the client sends no target state, so a
     * replayed request cannot publish something an admin had just taken down.
     * The updated row comes back so the caller reads the new state rather than
     * assuming its own guess landed.
     */
    public function toggle(int $id): JsonResponse
    {
        $record = $this->service()->togglePublished($id);

        return $this->successResponse(
            new ($this->resource())($record),
            $this->label().($record->is_published ? ' published.' : ' hidden.'),
        );
    }

    /**
     * Remove a row outright, along with any image it owns.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service()->delete($id);

        return $this->noContentResponse();
    }
}

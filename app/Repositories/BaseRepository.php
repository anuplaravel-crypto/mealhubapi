<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared Eloquent access for every repository.
 *
 * Concrete repositories declare their model and add only the queries that are
 * genuinely specific to their domain — the generic find/create/update/delete
 * shape lives here rather than being copy-pasted per class.
 *
 * Repositories return models and collections. They must not contain business
 * rules, and must never touch Request/Response objects, mail, or events.
 *
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /**
     * The Eloquent model this repository reads and writes.
     *
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * A fresh query builder for the repository's model.
     *
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): void
    {
        $model->delete();
    }
}

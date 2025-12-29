<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use RuntimeException;

/**¸
 * Abstract base repository providing common data-access methods.
 */
abstract class BaseRepository
{
    use HandleModelMethod;
    /**
     * The Eloquent model instance bound to this repository.
     */
    protected Model $model;

    /**
     * BaseRepository constructor.
     *
     */
    final public function __construct()
    {
        $this->model = $this->resolveModel();
    }


    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * @return Model
     */
    protected function resolveModel(): Model
    {
        $model = app($this->modelClass());

        if (! $model instanceof Model) {
            throw new RuntimeException(sprintf(
                'modelClass() must resolve to %s; got %s',
                Model::class,
                is_object($model) ? $model::class : gettype($model),
            ));
        }

        return $model;
    }

    /**
     * Fetch all records, optionally with pagination.
     *
     * @param  int|null  $perPage  If provided, return a paginator; otherwise return a Collection.
     * @return LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function all(?int $perPage = null)
    {
        if ($perPage) {
            return $this->model->paginate($perPage);
        }

        return $this->model->all();
    }

    /**
     * Create a new record with the given data.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing record by its primary key.
     *
     *
     * @throws ModelNotFoundException
     */
    public function update(int|string $id, array $data): Model
    {
        $record = $this->find($id);
        $record->update($data);

        return $record;
    }

    /**
     * Find a record by its primary key or fail.
     *
     *
     * @throws ModelNotFoundException
     */
    public function find(int|string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Delete a record by its primary key.
     *
     *
     * @throws ModelNotFoundException
     */
    public function delete(int|string $id): ?bool
    {
        return $this->find($id)->delete();
    }

    /**
     * Generic method to find a record by a specified field and value.
     *
     * @param  string  $field  Column name to search by.
     * @param  mixed  $value  Value to match.
     */
    public function findOneBy(string $field, mixed $value): ?Model
    {
        return $this->model->where($field, $value)->first();
    }

    /**
     * Find records matching multiple field=>value conditions.
     *
     * @param  array  $conditions  Associative array of field => value
     * @return Collection Eloquent collection of matching models
     */
    public function findWhere(array $conditions): Collection
    {
        $query = $this->model->newQuery();

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        // Return the Collection directly
        return $query->get();
    }

    /**
     * Find records matching multiple field => [values] conditions using whereIn.
     *
     * @param  array  $conditions  Associative array of field => array of values
     * @return Collection Eloquent collection of matching models
     */
    public function findWhereIn(array $conditions): Collection
    {
        $query = $this->model->newQuery();

        foreach ($conditions as $field => $values) {
            if (!is_array($values)) {
                throw new \InvalidArgumentException("Value for [$field] must be an array in findWhereIn().");
            }

            $query->whereIn($field, $values);
        }

        return $query->get();
    }

    /**
     * Return a new query builder for the model.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->model->newQuery();
    }

    /**
     * updateOrCreate a record based on the given attributes and values.
     */
    public function updateOrCreate(array $attributes, array $values = []): mixed
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    /**
     * upsert records based on the given attributes and values.
     *
     * @return Model
     */
    public function upsert(array $values, array $uniqueBy, array $update = []): mixed
    {
        return $this->model->upsert($values, $uniqueBy, $update);
    }

    public function fillQuery(array $filters, ?Builder $query = null): Builder
    {
        if ($query === null) {
            $query = $this->model->newQuery();
        }

        // Iterate over each filter and apply it to the query
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } elseif ($value !== null) {
                $query->where($key, $value);
            }
        }

        return $query;
    }

    /**
     * get or create Category (firstOrCreate)
     *
     * @param  array  $conditions
     * @param  array  $values
     * @return Model
     */
    public function firstOrCreate(array $conditions, array $values = [])
    {
        return $this->model->firstOrCreate($conditions, $values);
    }
    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }
    public function count(array $conditions = []): int
    {
        $query = $this->model->newQuery();

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->count();
    }
}

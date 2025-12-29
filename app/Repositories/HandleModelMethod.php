<?php

namespace App\Repositories;
use Illuminate\Database\Eloquent\Model;
trait HandleModelMethod
{
    /**
     * Safe forward to $this->model with guard rails:
     * - If the model has a callable method-> call.
     * - If the model has property -> return / invoke if callable.
     * - If the model is Eloquent -> allow Model::__call() to forward sang Builder/Scope.
     * - Else -> fallback string.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!isset($this->model) || !$this->model instanceof Model) {
            return $this->onMissingMember($name, $arguments);
        }

        if (is_callable([$this->model, $name])) {
            return $this->model->{$name}(...$arguments);
        }

        if (property_exists($this->model, $name)) {
            $value = $this->model->{$name};
            return (is_object($value) && is_callable($value)) ? $value(...$arguments) : $value;
        }

        try {
            return $this->model->{$name}(...$arguments);
        } catch (\Throwable $e) {
            return $this->onMissingMember($name, $arguments);
        }
    }

    /**
     * Fallback khi KHÔNG có method/property/magic phù hợp.
     * Tuỳ biến nếu bạn muốn trả code khác (JSON/error code, v.v.).
     */
    protected function onMissingMember(string $name, array $args): string
    {
        throw new \BadMethodCallException("Method or property '{$name}' does not exist on model " . get_class($this->model));
    }
}

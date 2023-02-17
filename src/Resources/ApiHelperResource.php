<?php

namespace Jonathannerat\LaravelApiHelper\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ApiHelperResource extends JsonResource {
    private string $paramColumns;
    private string $paramRelationships;

    protected $columns;
    protected $relationships;

    public function __construct($resource) {
        $columns = null;
        $relationships = null;

        if (is_array($resource)) {
            $columns = $resource[1] ?? null;
            $relationships = $resource[2] ?? null;
            $resource = $resource[0];
        }

        parent::__construct($resource);

        $this->columns = $columns;
        $this->relationships = $relationships;
        $this->paramColumns = config('api_helper.param_names.columns');
        $this->paramRelationships = config('api_helper.param_names.relationships');
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $res = [];

        // Load columns into result array
        $columns = $this->getColumns($request);

        if ($columns) {
            foreach ($columns as $column) {
                if (is_string($column)) {
                    $res[$column] = $this->{$column};
                }
            }
        } else {
            $res = parent::toArray($request);
        }

        // Load relationships into result array
        $relationships = $this->getRelationships($request);

        if ($relationships) {
            foreach ($relationships as $rel) {
                $relJson = json_decode($rel);
                $name = $rel;
                $columns = null;
                $relationships = null;

                if (json_last_error() === JSON_ERROR_NONE) {
                    $name = $relJson->name;
                    $columns = $relJson->columns ?? null;
                    $relationships = $relJson->relationships ?? null;
                } else {
                    if (strpos($rel, ':') !== false) {
                        $columns = str($rel)->after(':')->explode(',')->toArray();
                        $name = str($rel)->before(':')->toString();
                    }
                }

                $related = $this->{$name};

                if ($related instanceof Model) {
                    $res[$name] = new static([$related, $columns ?? false, $relationships ?? false]);
                } elseif ($related instanceof Collection) {
                    $res[$name] = static::collection($related->map(fn ($r) => [$r, $columns ?? false, $relationships ?? false]));
                }
            }
        }

        return $res;
    }

    /**
     * Get columns to include in the array resource
     *
     * @param Request $request
     * @return ?array<string|array>
     */
    protected function getColumns($request)
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        return $request->input($this->paramColumns, null);
    }

    protected function getRelationships($request)
    {
        if ($this->relationships !== null) {
            return $this->relationships;
        }

        return $request->input($this->paramRelationships, null);
    }
}

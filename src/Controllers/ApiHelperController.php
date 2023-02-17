<?php

namespace Jonathannerat\LaravelApiHelper\Controllers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Jonathannerat\LaravelApiHelper\Exceptions\InvalidQueryBuilder;
use Jonathannerat\LaravelApiHelper\Resources\ApiHelperResource;
use stdClass;

class ApiHelperController extends BaseController
{
    /** @var ?Model */
    protected $model = null;

    private string $paramColumns;
    private string $paramFilters;
    private string $paramRelationships;

    public function __construct()
    {
        $this->paramColumns = config('api_helper.param_names.columns');
        $this->paramFilters = config('api_helper.param_names.filters');
        $this->paramRelationships = config(
            'api_helper.param_names.relationships'
        );
    }

    public function index(Request $request)
    {
        $query = $this->getQueryBuilder();

        $request->validate([
            $this->paramColumns => 'nullable|array',
            $this->paramFilters => 'nullable|array',
            $this->paramRelationships => 'nullable|array'
        ]);

        if ($request->has($this->paramRelationships)) {
            if (!$query instanceof EloquentBuilder) {
                throw InvalidQueryBuilder::cantLoadRelationsWithNonEloquentBuilder();
            }

            $this->loadRelationships(
                $query,
                $request->input($this->paramRelationships)
            );
        }

        $this->selectColumns($query, $request->input($this->paramColumns, []));

        $this->applyFilters($query, $request->input($this->paramFilters, []));

        return ApiHelperResource::collection($query->get());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $query = $this->getQueryBuilder();
        $model = $query->find($id);

        $request->validate([
            $this->paramRelationships => 'nullable|array'
        ]);

        $this->loadRelationships(
            $model,
            $request->input($this->paramRelationships, [])
        );

        return ApiHelperResource::make($model);
    }

    /**
     * Get a query builder to find resources
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->model::query();
    }

    /**
     * Load relationships into query
     *
     * $relationships should be an array of either `rel_name:col_1,col_2,...` strings, or
     * JSON encoded objects describing the relationship:
     *
     * ```json
     * {
     *   "name": "rel_name",
     *   "columns": ["col_1", "col_2", ...],
     *   "filters": [...],
     * }
     * ```
     *
     * The later supports filters to be passed to the relationship, in case it applies
     *
     * @param EloquentBuilder $query Target query builder
     * @param (string|array)[] $relationships Relationships to load
     */
    protected function loadRelationships($query, $relationships)
    {
        foreach ($relationships as $rel) {
            if (is_array($rel)) {
                $query->with(
                    $rel['name'],
                    fn($q) => $this->selectColumns($q, $rel['columns'] ?? [])
                        ->applyFilters($q, $rel['filters'] ?? [])
                        ->loadRelationships($q, $rel['relationships'] ?? [])
                );
            } else {
                $query->with($rel);
            }
        }

        return $this;
    }

    /**
     * Add selected columns to the query
     *
     * @param QueryBuilder $query Target query builder
     * @param string[] $columns Columns to add
     */
    protected function selectColumns($query, $columns)
    {
        $query->select($columns);

        return $this;
    }

    const FILTER_NAMES = [
        'and' => [
            'where' => 'where',
            'null' => 'whereNull',
            'notNull' => 'whereNotNull',
            'whereRel' => 'whereRelation'
        ],
        'or' => [
            'where' => 'orWhere',
            'null' => 'orWhereNull',
            'notNull' => 'orWhereNotNull',
            'whereRel' => 'orWhereRelation'
        ]
    ];

    /**
     * Apply filters specified to the query
     *
     * $filters is an array of either strings or arrays. If we specify N
     * conditions / subfilters, then $filters should have N-1 string elements
     * in between, specifying how the conditions are joined ('and' | 'or'). That
     * means $filters has an odd number of elements.
     *
     * If a $filter in $filters is a string, it's either 'and', 'or', or a json
     * string that describes a condition. In the later case, the json contains
     * 2 properties: `type` and `args`:
     * - `type` indicates what kind of condition to apply: `where`, `whereNull`,
     *   `whereRelation`, etc.
     * - `args` indicates the arguments to use in the call to the appropiate
     *   `type` method in the query
     *
     * If $filters is an array, then it contains a list of subfilters to add to
     * the query. This is useful to apply complex filters:
     *
     * ```sql
     * ... WHERE `name` = 'John' AND (`age` > 18 OR `age` < 60)
     * ```
     *
     * Here, the second term would be a list of subfilters:
     *
     * @param QueryBuilder $query Target query builder
     * @param array<array|string> $filters array of filters to apply
     * @return
     */
    protected function applyFilters($query, $filters)
    {
        $nextOp = 'and';

        foreach ($filters as $filter) {
            if ($filter === 'and' || $filter === 'or') {
                $nextOp = $filter;
                continue;
            }

            $type = $filter['type'] ?? false;
            $opName = self::FILTER_NAMES[$nextOp][$type ?: 'where'];

            if ($type) {
                $query->{$opName}(...$filter['args']);
            } else {
                $query->{$opName}(fn($q) => $this->applyFilters($q, $filter));
            }
        }

        return $this;
    }
}

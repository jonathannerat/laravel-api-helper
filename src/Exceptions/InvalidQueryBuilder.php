<?php

namespace Jonathannerat\LaravelApiHelper\Exceptions;

use Exception;

class InvalidQueryBuilder extends Exception {
    public static function cantLoadRelationsWithNonEloquentBuilder(): self
    {
        return new static("Can't eager load relations using a normal query builder, should use eloquent builder instead.");
    }
}

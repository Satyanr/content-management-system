<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
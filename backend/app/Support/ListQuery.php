<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class ListQuery
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>|LengthAwarePaginator<int, TModel>
     */
    public static function paginateOrAll(Builder $query, Request $request, int $defaultPerPage = 15): Collection|LengthAwarePaginator
    {
        if ($request->boolean('all') || $request->integer('per_page') === -1) {
            return $query->get();
        }

        return $query->paginate($request->integer('per_page', $defaultPerPage));
    }
}

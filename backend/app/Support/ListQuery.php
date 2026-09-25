<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

final class ListQuery
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>|Relation<TModel, covariant \Illuminate\Database\Eloquent\Model, mixed>  $query
     * @return Collection<int, TModel>|LengthAwarePaginator<TModel>
     */
    public static function paginateOrAll(Builder|Relation $query, Request $request, int $defaultPerPage = 15): Collection|LengthAwarePaginator
    {
        if ($request->boolean('all') || $request->integer('per_page') === -1) {
            return $query->get();
        }

        return $query->paginate($request->integer('per_page', $defaultPerPage));
    }
}

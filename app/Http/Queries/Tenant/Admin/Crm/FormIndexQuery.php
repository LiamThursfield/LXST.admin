<?php

namespace App\Http\Queries\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\FormIndexRequest;
use App\Models\Crm\Form;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class FormIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Form>
     */
    public function handle(FormIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Form::query()->withCount('fields'), $request)
            ->allowedFilters(
                'name',
            )
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}

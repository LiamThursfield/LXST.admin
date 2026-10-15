<?php

namespace App\Http\Queries\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\FormSubmissionIndexRequest;
use App\Models\Crm\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FormSubmissionIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, FormSubmission>
     */
    public function handle(FormSubmissionIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(FormSubmission::query()->with('form', 'contact'), $request)
            ->allowedFilters(
                AllowedFilter::exact('form_id'),
                AllowedFilter::exact('contact_id'),
                AllowedFilter::exact('is_spam'),
                // Inclusive date range, by calendar day
                AllowedFilter::callback('submitted_from', fn (Builder $query, mixed $value) => $query->whereDate('submitted_at', '>=', (string) $value)),
                AllowedFilter::callback('submitted_to', fn (Builder $query, mixed $value) => $query->whereDate('submitted_at', '<=', (string) $value)),
            )
            ->defaultSort('-submitted_at')
            ->allowedSorts(
                'submitted_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}

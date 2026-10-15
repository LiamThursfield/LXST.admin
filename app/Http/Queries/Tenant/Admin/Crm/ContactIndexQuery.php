<?php

namespace App\Http\Queries\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\ContactIndexRequest;
use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContactIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public function handle(ContactIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Contact::class, $request)
            ->allowedFilters(
                // Matches the name, email or telephone
                AllowedFilter::callback('search', function (Builder $query, mixed $value) {
                    $term = '%'.(is_array($value) ? implode(' ', $value) : (string) $value).'%';

                    $query->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('telephone', 'like', $term)
                    );
                }),
                AllowedFilter::exact('is_spam'),
                AllowedFilter::trashed(),
            )
            ->defaultSort('-created_at')
            ->allowedSorts(
                'first_name',
                'last_name',
                'email',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}

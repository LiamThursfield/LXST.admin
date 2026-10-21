<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\AnnualDropQuery;
use App\Http\Requests\Tenant\Admin\Dj\AnnualDropRequest;
use App\Models\Settings\DjSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Annual Drop (#127): a yearly (or any period's) wrap-up of everything played
 */
class AnnualDropController extends Controller
{
    public function index(AnnualDropRequest $request, AnnualDropQuery $query, DjSettings $settings): Response
    {
        [$from, $to] = $request->period();
        $scope = $request->scope();

        return Inertia::render('admin/dj/AnnualDrop', [
            'filter' => [
                'year' => $request->filled('from') ? null : $request->year(),
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
                'scope' => $scope,
            ],
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'years' => $query->years(),
            'timezone' => $settings->default_timezone,
            'drop' => $query->handle($from, $to, $scope === AnnualDropRequest::SCOPE_GIGS),
        ]);
    }
}

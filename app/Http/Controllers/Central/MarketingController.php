<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public site on the central domain: the home page and a page for each module.
 * Its copy lives in lang/en/marketing.php.
 */
class MarketingController extends Controller
{
    /**
     * The address of each module's page, in the order the site lists them (as in resources/js/lib/marketing.ts).
     * They use the names customers know, so the CMS is "website" and the CRM "customers".
     */
    public const array MODULES = ['bookings', 'website', 'customers', 'catalogue', 'tracker', 'dj'];

    public function home(): Response
    {
        return Inertia::render('marketing/Home');
    }

    public function module(string $module): Response
    {
        return Inertia::render('marketing/Module', [
            'module' => $module,
        ]);
    }
}

<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Redirector;

/**
 * For a "request" made from one imported item, so it goes through the same rules as the admin's forms.
 * Whether the user can import it is checked by the import.
 *
 * @template TModel of Model
 */
trait IsImportedData
{
    /** @var TModel|null */
    protected ?Model $existing = null;

    /**
     * @param  array<string, mixed>  $data  As the admin's form would send it
     * @param  TModel|null  $existing
     */
    public static function for(array $data, ?Model $existing): self
    {
        $request = new self([], $data);
        $request->setMethod('POST');
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));
        $request->existing = $existing;

        return $request;
    }
}

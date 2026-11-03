<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesTemplate;
use App\Models\Cms\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;

/**
 * One imported template, put through the same rules as the template builder. It isn't a real request:
 * pasted JSON can't be trusted any more than a form, so it's checked in exactly the same way. Whether
 * the user can import is checked by the import's own request.
 */
class ImportedTemplateData extends FormRequest
{
    use ValidatesTemplate;

    protected ?Template $existing = null;

    /**
     * @param  array<string, mixed>  $data  As the template builder would send it
     */
    public static function for(array $data, ?Template $existing): self
    {
        $request = new self([], $data);
        $request->setMethod('POST');
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));
        $request->existing = $existing;

        return $request;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->templateRules($this->existing);
    }
}

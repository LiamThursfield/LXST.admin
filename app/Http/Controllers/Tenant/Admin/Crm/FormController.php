<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Enums\Crm\FormFieldType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Crm\FormIndexQuery;
use App\Http\Requests\Tenant\Admin\Crm\FormIndexRequest;
use App\Http\Requests\Tenant\Admin\Crm\FormStoreRequest;
use App\Http\Requests\Tenant\Admin\Crm\FormUpdateRequest;
use App\Http\Resources\Tenant\Admin\Crm\FormResource;
use App\Models\Crm\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function index(FormIndexRequest $request, FormIndexQuery $query): Response
    {
        return Inertia::render('admin/crm/form/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('crm.forms.columns.name')],
                ['accessorKey' => 'slug', 'header' => __('crm.forms.columns.slug')],
                ['accessorKey' => 'fields_count', 'header' => __('crm.forms.columns.fields')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'forms' => FormResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', Form::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Form::class);

        return Inertia::render('admin/crm/form/Create', $this->formOptions());
    }

    public function store(FormStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $form = Form::create($request->formAttributes());

            $this->syncFields($form, $request->fieldsAttributes());
        });

        return redirect()->route('admin.crm.forms.index')
            ->with('success', __('crm.forms.flash.created'));
    }

    public function show(Form $form): Response
    {
        Gate::authorize('view', $form);

        return Inertia::render('admin/crm/form/Show', [
            'form' => new FormResource($form->load('fields')),
            ...$this->formOptions(),
        ]);
    }

    public function edit(Form $form): Response
    {
        Gate::authorize('update', $form);

        return Inertia::render('admin/crm/form/Edit', [
            'form' => new FormResource($form->load('fields')),
            ...$this->formOptions(),
        ]);
    }

    public function update(FormUpdateRequest $request, Form $form): RedirectResponse
    {
        DB::transaction(function () use ($request, $form) {
            $form->update($request->formAttributes());

            $this->syncFields($form, $request->fieldsAttributes());
        });

        return redirect()->route('admin.crm.forms.index')
            ->with('success', __('crm.forms.flash.updated'));
    }

    /**
     * Soft deletes the form; its fields are kept with it
     */
    public function destroy(Form $form): RedirectResponse
    {
        Gate::authorize('delete', $form);

        $form->delete();

        return redirect()->back()->with('success', __('crm.forms.flash.deleted'));
    }

    /**
     * The select options the form builder needs
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'fieldTypes' => collect(FormFieldType::cases())->map(fn (FormFieldType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'isContactField' => $type->isContactField(),
                'settingKeys' => $type->settingKeys(),
            ])->values(),
        ];
    }

    /**
     * Makes the form's fields match the submitted list: existing fields (by id) are updated,
     * new ones created and any not in the list removed
     *
     * @param  list<array<string, mixed>>  $fields
     */
    protected function syncFields(Form $form, array $fields): void
    {
        $keptIds = collect($fields)->pluck('id')->filter()->all();

        $form->fields()->whereNotIn('id', $keptIds)->delete();

        // Free up the kept fields' slugs first, so fields can swap slugs without hitting the unique index
        $form->fields()->whereIn('id', $keptIds)->update(['slug' => DB::raw("CONCAT('__', id)")]);

        foreach ($fields as $field) {
            $attributes = Arr::except($field, 'id');

            if ($field['id'] !== null) {
                $form->fields()->whereKey($field['id'])->firstOrFail()->update($attributes);
            } else {
                $form->fields()->create($attributes);
            }
        }
    }
}

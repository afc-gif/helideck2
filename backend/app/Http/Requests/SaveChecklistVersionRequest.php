<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveChecklistVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1', 'max:100'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:5000'],
            'sections.*.questions' => ['present', 'array', 'max:100'],
            'sections.*.questions.*.stable_key' => ['nullable', 'uuid', 'distinct'],
            'sections.*.questions.*.label' => ['required', 'string', 'max:500'],
            'sections.*.questions.*.description' => ['nullable', 'string', 'max:5000'],
            'sections.*.questions.*.instructions' => ['nullable', 'string', 'max:5000'],
            'sections.*.questions.*.field_type' => ['required', 'string', 'in:text,textarea,integer,decimal,date,time,boolean,single_select,multi_select,informational'],
            'sections.*.questions.*.is_required' => ['required', 'boolean'],
            'sections.*.questions.*.unit' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.options' => ['present', 'array', 'max:100'],
            'sections.*.questions.*.options.*.value' => ['required', 'string', 'max:255'],
            'sections.*.questions.*.options.*.label' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('sections', []) as $sectionIndex => $section) {
                foreach ($section['questions'] ?? [] as $questionIndex => $question) {
                    $field = "sections.{$sectionIndex}.questions.{$questionIndex}";
                    $requiresOptions = in_array($question['field_type'] ?? null, ['single_select', 'multi_select'], true);
                    $optionCount = count($question['options'] ?? []);

                    if ($requiresOptions && $optionCount === 0) {
                        $validator->errors()->add("{$field}.options", 'Add at least one response option for this question type.');
                    }
                    if (! $requiresOptions && $optionCount > 0) {
                        $validator->errors()->add("{$field}.options", 'Response options are only available for single-select and multi-select questions.');
                    }
                }
            }
        }];
    }
}

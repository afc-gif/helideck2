<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSurveyAnswersRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:in_progress,completed'],
            'answers' => ['required', 'array', 'max:500'],
            'answers.*.question_public_id' => ['required', 'uuid', 'distinct'],
            'answers.*.value' => ['present'],
            'answers.*.comment' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

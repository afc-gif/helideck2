<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyInstanceRequest extends FormRequest
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
            'checklist_public_id' => ['required', 'uuid'],
            'version_number' => ['required', 'integer', 'min:1'],
            'site_name' => ['required', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Person\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

class PersonSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}

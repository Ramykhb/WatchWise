<?php

namespace App\User\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:2048'],
        ];
    }

    public function avatar(): UploadedFile
    {
        return $this->file('avatar');
    }
}

<?php

namespace App\User\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;

class WatchedHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cursor' => ['sometimes', 'string', 'max:2048']];
    }

    public function cursor(): ?Cursor
    {
        if (! $this->filled('cursor')) {
            return null;
        }
        try {
            $cursor = Cursor::fromEncoded($this->validated('cursor'));
            if ($cursor === null || ! is_numeric($cursor->parameter('id')) || ! is_string($cursor->parameter('watched_order'))) {
                throw new \UnexpectedValueException;
            }

            return $cursor;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['cursor' => 'Invalid history cursor.']);
        }
    }
}

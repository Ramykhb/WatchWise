<?php

namespace App\Watchlist\FormRequests;

use App\Models\Watchlist;
use App\Watchlist\DTOs\Requests\AddToWatchlistData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Watchlist::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(AddToWatchlistData::STATUSES)],
        ];
    }

    public function toDto(): AddToWatchlistData
    {
        return AddToWatchlistData::fromValidated($this->validated());
    }
}

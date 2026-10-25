<?php

namespace App\Watchlist\FormRequests;

use App\Watchlist\DTOs\Requests\AddToWatchlistData;
use App\Watchlist\DTOs\Requests\UpdateWatchlistStatusData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('watchlist'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AddToWatchlistData::STATUSES)],
        ];
    }

    public function toDto(): UpdateWatchlistStatusData
    {
        return UpdateWatchlistStatusData::fromValidated($this->validated());
    }
}

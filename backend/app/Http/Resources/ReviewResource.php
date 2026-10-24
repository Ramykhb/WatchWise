<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdb_id,
            'rating' => $this->rating,
            'sentiment' => $this->sentiment,
            'content' => $this->content,
            'author' => $this->relationLoaded('user') ? new MemberResource($this->user) : null,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}

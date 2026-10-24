<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 *
 * `data` holds the sentence parts written by the Notifier service. The actor is not a real
 * relation (it lives in `data.actor_id`); NotificationController::withActors() attaches it.
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        $actor = $this->resource->relationLoaded('actor') ? $this->resource->getRelation('actor') : null;

        return [
            'id' => $this->id,
            'kind' => $this->type,
            'actor' => $actor instanceof User ? new MemberResource($actor) : null,
            // Whether the recipient follows the actor (attached with the actor by the controller).
            'actor_followed' => $this->resource->relationLoaded('actorFollowed') && $this->resource->getRelation('actorFollowed') === true,
            'text' => $data['text'] ?? '',
            'target' => $data['target'] ?? null,
            'detail' => $data['detail'] ?? null,
            'to' => $data['to'] ?? '/notifications',
            'action' => $data['action'] ?? 'Open',
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}

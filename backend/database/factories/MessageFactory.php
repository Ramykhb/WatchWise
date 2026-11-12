<?php

namespace Database\Factories;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * A message from a new member in a new room, with no read receipts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => DiscussionRoom::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
        ];
    }
}

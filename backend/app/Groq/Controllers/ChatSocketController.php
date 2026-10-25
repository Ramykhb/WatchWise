<?php

namespace App\Groq\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Groq\DTOs\Requests\ChatMessageData;
use App\Groq\FormRequests\ChatSocketRequest;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Http\JsonResponse;

class ChatSocketController
{
    public function __construct(private readonly Factory $broadcasting) {}

    public function store(ChatSocketRequest $request, CurrentUser $current): JsonResponse
    {
        $request->setUserResolver(fn () => $current->user());

        return response()->json($this->broadcasting->connection('reverb')->auth($request));
    }

    /** Public connection settings only; no provider key or Reverb secret. */
    public function show(): JsonResponse
    {
        return response()->json([
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.public.host'),
            'port' => config('broadcasting.connections.reverb.public.port'),
            'scheme' => config('broadcasting.connections.reverb.public.scheme'),
            'max_message_chars' => ChatMessageData::MAX_MESSAGE_CHARS,
            'max_history_messages' => ChatMessageData::MAX_HISTORY_MESSAGES,
            'max_history_chars' => ChatMessageData::MAX_HISTORY_CHARS,
        ]);
    }
}

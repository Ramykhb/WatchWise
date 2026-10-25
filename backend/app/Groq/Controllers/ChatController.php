<?php

namespace App\Groq\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Groq\FormRequests\ChatMessageRequest;
use App\Groq\InterfaceServices\ChatServiceInterface;
use Illuminate\Http\JsonResponse;

class ChatController
{
    public function __construct(private readonly ChatServiceInterface $chat) {}

    public function store(ChatMessageRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->chat->reply($request->toDto(), $current->id()));
    }
}

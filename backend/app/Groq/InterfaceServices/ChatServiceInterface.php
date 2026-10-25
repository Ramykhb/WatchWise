<?php

namespace App\Groq\InterfaceServices;

use App\Groq\DTOs\Requests\ChatMessageData;
use App\Groq\DTOs\Responses\ChatResponse;

interface ChatServiceInterface
{
    public function reply(ChatMessageData $data, int $userId): ChatResponse;
}

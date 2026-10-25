<?php

namespace App\Groq\InterfaceServices;

use App\Groq\Exceptions\GroqUnavailable;

interface GroqChatServiceInterface
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onDelta
     *
     * @throws GroqUnavailable
     */
    public function streamChat(array $messages, callable $onDelta): string;
}

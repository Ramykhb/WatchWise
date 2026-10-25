<?php

namespace App\Groq\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use App\Groq\DTOs\Requests\ChatMessageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentUser::class)->isAuthenticated();
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.ChatMessageData::MAX_MESSAGE_CHARS],
            'conversation_id' => ['required', 'uuid'],
            'request_id' => ['required', 'uuid'],
            'history' => ['sometimes', 'array', 'list', 'max:'.ChatMessageData::MAX_HISTORY_MESSAGES],
            'history.*' => ['required', 'array:role,content'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:8000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $history = $this->input('history', []);
            $characters = 0;
            foreach ($history as $index => $message) {
                $characters += mb_strlen($message['content']);
                if ($message['role'] !== ($index % 2 === 0 ? 'user' : 'assistant')) {
                    $validator->errors()->add('history', 'History must contain completed user and assistant turns in order.');
                    break;
                }
            }
            if (count($history) % 2 !== 0) {
                $validator->errors()->add('history', 'History must end with an assistant reply.');
            }
            if ($characters > ChatMessageData::MAX_HISTORY_CHARS) {
                $validator->errors()->add('history', 'The conversation history is too long.');
            }
        }];
    }

    public function toDto(): ChatMessageData
    {
        return ChatMessageData::fromValidated($this->validated());
    }
}

<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatMessage;

/**
 * Het geheugen van de chat: waar de gespreksgeschiedenis leeft tussen twee requests in.
 */
interface ConversationStore
{
    /**
     * @return list<ChatMessage> alle berichten van het actieve gesprek, in volgorde
     */
    public function messages(): array;

    public function append(ChatMessage ...$messages): void;

    /**
     * Start een nieuw, leeg gesprek.
     */
    public function clear(): void;
}

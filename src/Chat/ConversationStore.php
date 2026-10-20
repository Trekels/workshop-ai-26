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
     * @return list<ChatMessage> de berichten die nog in de context zitten (dus zonder de gecompacteerde), in volgorde
     */
    public function messages(): array;

    public function append(ChatMessage ...$messages): void;

    /**
     * Samenvatting van het gecompacteerde deel van het gesprek, of null zolang er niets gecompacteerd is.
     */
    public function summary(): ?string;

    /**
     * Vervang alles behalve de laatste $keepRecent berichten door deze samenvatting.
     */
    public function compact(string $summary, int $keepRecent): void;

    /**
     * Start een nieuw, leeg gesprek.
     */
    public function clear(): void;
}

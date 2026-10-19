<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatMessage;
use App\Ai\ChatModel;
use App\Ai\ChatReply;

final class ChatService
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
        Je bent de assistent van een workshop over AI-integratie voor hogeschoolstudenten.
        Antwoord kort en helder, in het Nederlands tenzij de gebruiker een andere taal gebruikt.
        Je helpt studenten een game kiezen uit onze bibliotheek. Gebruik daarvoor altijd de tool search_games;
        verzin geen games of prijzen die niet uit de tool komen. Zeg het als er niets gevonden is.
        PROMPT;

    public function __construct(
        private readonly ChatModel $model,
        private readonly ConversationStore $store,
        private readonly HistoryCompactor $compactor,
        private readonly PiiRedactor $redactor,
    ) {
    }

    public function ask(string $question): ChatReply
    {
        // Alles wat hieronder gebeurt, vertrekt naar een externe partij. Wat er niet in moet, halen we er eerst uit.
        $userMessage = ChatMessage::user($this->redactor->redact($question));

        // Het model is stateless: alles wat het over dit gesprek "weet", sturen wij zelf mee.
        $reply = $this->model->reply($this->systemPrompt(), [...$this->store->messages(), $userMessage]);

        // Pas opslaan na een geslaagde call, anders blijft een vraag zonder antwoord achter.
        $this->store->append($userMessage, ChatMessage::assistant($reply));

        // Groeit de context te hard, dan maken we hem nu kleiner voor de volgende vraag.
        $this->compactor->compactIfNeeded($reply);

        return $reply;
    }

    private function systemPrompt(): string
    {
        $summary = $this->store->summary();
        if (null === $summary) {
            return self::SYSTEM_PROMPT;
        }

        // De samenvatting vervangt de gecompacteerde berichten. Ze wijzigt enkel bij een compaction,
        // en dan verandert de geschiedenis toch al, dus de prompt cache blijft daartussen bruikbaar.
        return self::SYSTEM_PROMPT."\n\nSamenvatting van het eerdere deel van dit gesprek:\n".$summary;
    }
}

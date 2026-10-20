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
    ) {
    }

    public function ask(string $question): ChatReply
    {
        $userMessage = ChatMessage::user($question);

        // Het model is stateless: alles wat het over dit gesprek "weet", sturen wij zelf mee.
        $reply = $this->model->reply(self::SYSTEM_PROMPT, [...$this->store->messages(), $userMessage]);

        // Pas opslaan na een geslaagde call, anders blijft een vraag zonder antwoord achter.
        $this->store->append($userMessage, ChatMessage::assistant($reply));

        return $reply;
    }
}

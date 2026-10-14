<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatModel;
use App\Ai\ChatReply;

final class ChatService
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
        Je bent de assistent van een workshop over AI-integratie voor hogeschoolstudenten.
        Antwoord kort en helder, in het Nederlands tenzij de gebruiker een andere taal gebruikt.
        PROMPT;

    public function __construct(
        private readonly ChatModel $model,
        private readonly ConversationStore $store,
    ) {
    }

    public function ask(string $question): ChatReply
    {
        // TODO laag 2: het model is stateless, dus alles wat het over dit gesprek "weet" sturen wij zelf mee.
        //
        // 1. Maak van $question een ChatMessage::user(...).
        // 2. Stuur [...$this->store->messages(), $vraag] naar $this->model->reply(self::SYSTEM_PROMPT, ...).
        // 3. Bewaar vraag én antwoord (ChatMessage::assistant($reply)) via $this->store->append(...) en geef $reply terug.
        //    Tip: pas opslaan na een geslaagde call, anders blijft een vraag zonder antwoord achter.
        throw new \LogicException('Laag 2: implementeer ChatService::ask()');
    }
}

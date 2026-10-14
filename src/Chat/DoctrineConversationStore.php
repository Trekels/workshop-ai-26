<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatMessage;
use App\Entity\Conversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Geheugen in SQLite. De sessie onthoudt enkel wélk gesprek van deze browser is.
 */
final class DoctrineConversationStore implements ConversationStore
{
    private const string SESSION_KEY = 'chat.conversation_id';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function messages(): array
    {
        // TODO laag 2: haal het actieve gesprek op (zie currentConversation()) en geef zijn berichten
        // terug als ChatMessage-objecten. Tip: Message::toChatMessage(). Geen gesprek = lege lijst.
        return [];
    }

    public function append(ChatMessage ...$messages): void
    {
        // TODO laag 2:
        // 1. Neem het actieve gesprek, of maak een nieuwe Conversation en persist die.
        // 2. Maak per ChatMessage een Message-entity: Message::fromChatMessage($conversation, $message).
        // 3. Eén flush voor alles samen, en onthoud daarna het id van het gesprek in de sessie.
        throw new \LogicException('Laag 2: implementeer DoctrineConversationStore::append()');
    }

    public function clear(): void
    {
        // TODO laag 2: vergeet het actieve gesprek in de sessie. Wat gebeurt er met de data in de database?
        throw new \LogicException('Laag 2: implementeer DoctrineConversationStore::clear()');
    }

    private function currentConversation(): ?Conversation
    {
        $id = $this->session()->get(self::SESSION_KEY);

        return \is_int($id) ? $this->entityManager->find(Conversation::class, $id) : null;
    }

    private function session(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}

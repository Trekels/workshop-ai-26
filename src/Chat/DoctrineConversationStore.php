<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatMessage;
use App\Entity\Conversation;
use App\Entity\Message;
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
        $conversation = $this->currentConversation();
        if (null === $conversation) {
            return [];
        }

        return array_map(static fn (Message $m): ChatMessage => $m->toChatMessage(), $conversation->getMessages());
    }

    public function append(ChatMessage ...$messages): void
    {
        $conversation = $this->currentConversation();
        if (null === $conversation) {
            $conversation = new Conversation();
            $this->entityManager->persist($conversation);
        }

        foreach ($messages as $message) {
            Message::fromChatMessage($conversation, $message);
        }

        // Eén flush per unit of work: gesprek en berichten gaan samen naar de database.
        $this->entityManager->flush();
        $this->session()->set(self::SESSION_KEY, $conversation->getId());
    }

    public function clear(): void
    {
        // Let op: dit vergeet enkel welk gesprek actief is. De berichten blijven in de database (zie laag 6).
        $this->session()->remove(self::SESSION_KEY);
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

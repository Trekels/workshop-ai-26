<?php

declare(strict_types=1);

namespace App\Chat;

use App\Ai\ChatMessage;
use App\Ai\ChatModel;
use App\Ai\ChatReply;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compaction: wordt het gesprek te groot, dan vatten we het oudste deel samen en sturen we enkel nog
 * de samenvatting plus de recentste berichten naar het model.
 */
final class HistoryCompactor
{
    /**
     * Hoeveel berichten we letterlijk bewaren: de laatste twee vraag-antwoordparen.
     */
    private const int KEEP_RECENT = 4;

    private const string SUMMARY_PROMPT = <<<'PROMPT'
        Je vat een gesprek samen voor een assistent die het straks verderzet zonder de originele berichten.
        Behoud wat die assistent nodig heeft: wat de gebruiker zoekt (genre, aantal spelers, budget, platform),
        welke games al voorgesteld of afgewezen zijn en waarom, en wat nog openstaat.
        Antwoord enkel met de samenvatting, in het Nederlands, maximaal 150 woorden. Gebruik geen tools.
        PROMPT;

    public function __construct(
        private readonly ChatModel $model,
        private readonly ConversationStore $store,
        #[Autowire(env: 'int:CHAT_COMPACT_AFTER_TOKENS')]
        public readonly int $compactAfterTokens,
    ) {
    }

    /**
     * Roep dit op na elk antwoord: de usage van dat antwoord vertelt hoe groot de context intussen is.
     */
    public function compactIfNeeded(ChatReply $lastReply): void
    {
        if ($lastReply->promptTokens() < $this->compactAfterTokens) {
            return;
        }

        $messages = $this->store->messages();
        if (\count($messages) <= self::KEEP_RECENT) {
            return;
        }

        // Een samenvatting is een gewone call: het model krijgt de oude berichten en antwoordt met de samenvatting.
        $old = \array_slice($messages, 0, -self::KEEP_RECENT);
        $summary = $this->model->reply($this->summaryPrompt(), [
            ...$old,
            ChatMessage::user('Vat het gesprek hierboven samen.'),
        ]);

        $this->store->compact($summary->text, self::KEEP_RECENT);
    }

    private function summaryPrompt(): string
    {
        $previous = $this->store->summary();
        if (null === $previous) {
            return self::SUMMARY_PROMPT;
        }

        // Anders verliezen we bij een tweede compaction wat in de eerste samenvatting zat.
        return self::SUMMARY_PROMPT."\n\nDit was al samengevat, neem het mee in de nieuwe samenvatting:\n".$previous;
    }
}

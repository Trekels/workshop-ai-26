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
        // TODO laag 4, stap 2: compaction.
        // 1. Niets doen zolang $lastReply->promptTokens() onder $this->compactAfterTokens blijft,
        //    of zolang er niet meer dan KEEP_RECENT berichten zijn.
        // 2. Neem alle berichten behalve de laatste KEEP_RECENT (array_slice) en laat het model ze
        //    samenvatten: $this->model->reply(SUMMARY_PROMPT, [...$oud, ChatMessage::user('Vat ... samen.')]).
        //    Is er al een samenvatting (store->summary()), geef die dan mee zodat ze niet verloren gaat.
        // 3. $this->store->compact($samenvatting, self::KEEP_RECENT).
    }
}

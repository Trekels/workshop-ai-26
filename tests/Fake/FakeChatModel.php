<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Ai\ChatMessage;
use App\Ai\ChatModel;
use App\Ai\ChatReply;

/**
 * Vervangt Claude in tests: geen netwerk, geen key, en we kunnen zien wat er naar het model ging.
 */
final class FakeChatModel implements ChatModel
{
    /** @var list<list<ChatMessage>> */
    public array $receivedHistories = [];

    public function __construct(
        private readonly string $answer = 'Dit is een testantwoord.',
    ) {
    }

    public function reply(string $systemPrompt, array $history): ChatReply
    {
        $this->receivedHistories[] = $history;

        return new ChatReply(
            text: $this->answer,
            model: 'fake-model',
            stopReason: 'end_turn',
            inputTokens: 12,
            outputTokens: 7,
        );
    }
}

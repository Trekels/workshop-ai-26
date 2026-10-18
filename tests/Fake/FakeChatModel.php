<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Ai\ChatMessage;
use App\Ai\ChatModel;
use App\Ai\ChatReply;
use App\Ai\ToolCall;

/**
 * Vervangt Claude in tests: geen netwerk, geen key, en we kunnen zien wat er naar het model ging.
 */
final class FakeChatModel implements ChatModel
{
    /** @var list<string> */
    public array $receivedSystemPrompts = [];

    /** @var list<list<ChatMessage>> */
    public array $receivedHistories = [];

    /** @var list<ToolCall> */
    public array $toolCalls = [];

    public int $inputTokens = 12;
    public int $cacheReadTokens = 0;

    /** @var list<string> */
    private array $answers;

    /**
     * Elke call geeft het volgende antwoord uit de lijst; het laatste blijft gelden als de lijst op is.
     */
    public function __construct(string ...$answers)
    {
        $this->answers = [] === $answers ? ['Dit is een testantwoord.'] : array_values($answers);
    }

    public function answerWith(string ...$answers): void
    {
        $this->answers = array_values($answers);
    }

    public function reply(string $systemPrompt, array $history): ChatReply
    {
        $this->receivedSystemPrompts[] = $systemPrompt;
        $this->receivedHistories[] = $history;

        $answer = \count($this->answers) > 1 ? array_shift($this->answers) : $this->answers[0];

        return new ChatReply(
            text: $answer,
            model: 'fake-model',
            stopReason: 'end_turn',
            inputTokens: $this->inputTokens,
            outputTokens: 7,
            toolCalls: $this->toolCalls,
            cacheReadTokens: $this->cacheReadTokens,
        );
    }
}

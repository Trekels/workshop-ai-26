<?php

declare(strict_types=1);

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Messages\TextBlock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ClaudeChatModel implements ChatModel
{
    private const int MAX_TOKENS = 16000;

    public function __construct(
        private readonly Client $client,
        #[Autowire(env: 'CLAUDE_MODEL')]
        private readonly string $model,
    ) {
    }

    public function reply(string $systemPrompt, string $question): ChatReply
    {
        // Dit is de hele "magie": één HTTP-call met een system prompt en de vraag.
        $message = $this->client->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: [['role' => 'user', 'content' => $question]],
            model: $this->model,
            system: $systemPrompt,
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        if ('refusal' === $message->stopReason) {
            $text = $message->stopDetails->explanation ?? 'Het model weigerde deze vraag te beantwoorden.';
        }

        return new ChatReply(
            text: $text,
            model: $message->model,
            stopReason: $message->stopReason,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
        );
    }
}

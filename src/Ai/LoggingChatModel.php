<?php

declare(strict_types=1);

namespace App\Ai;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Wat verlaat je server? Deze decorator zit rond elk ChatModel en schrijft alles wat naar het model gaat
 * en terugkomt naar var/log/ai.log. Lees dat bestand eens na een paar vragen.
 */
#[AsDecorator(decorates: ChatModel::class)]
#[WithMonologChannel('ai')]
final class LoggingChatModel implements ChatModel
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ChatModel $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reply(string $systemPrompt, array $history): ChatReply
    {
        $lines = ['→ naar het model', 'system: '.$systemPrompt];
        foreach ($history as $message) {
            $lines[] = $message->role->value.': '.$message->content;
        }
        $this->logger->info(implode("\n", $lines));

        $reply = $this->inner->reply($systemPrompt, $history);

        $lines = [sprintf(
            '← van het model (%s, stop: %s, in: %d, uit: %d, cache: %d gelezen / %d geschreven)',
            $reply->model,
            $reply->stopReason ?? '-',
            $reply->inputTokens,
            $reply->outputTokens,
            $reply->cacheReadTokens,
            $reply->cacheCreationTokens,
        )];
        foreach ($reply->toolCalls as $call) {
            $input = json_encode($call->input, \JSON_UNESCAPED_UNICODE);
            $lines[] = sprintf('tool %s(%s) → %s', $call->name, $input, $call->result);
        }
        $lines[] = 'assistant: '.$reply->text;
        $this->logger->info(implode("\n", $lines));

        return $reply;
    }
}

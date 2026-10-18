<?php

declare(strict_types=1);

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Messages\MessageParam;
use Anthropic\Messages\RedactedThinkingBlock;
use Anthropic\Messages\RedactedThinkingBlockParam;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextBlockParam;
use Anthropic\Messages\ThinkingBlock;
use Anthropic\Messages\ThinkingBlockParam;
use Anthropic\Messages\Tool as ToolDefinition;
use Anthropic\Messages\ToolResultBlockParam;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\ToolUseBlockParam;
use App\Ai\Tool\Tool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ClaudeChatModel implements ChatModel
{
    private const int MAX_TOKENS = 16000;
    private const int MAX_TOOL_ROUNDS = 10;

    /** @var array<string, Tool> */
    private readonly array $tools;

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(
        private readonly Client $client,
        #[Autowire(env: 'CLAUDE_MODEL')]
        private readonly string $model,
        #[AutowireIterator('app.ai_tool')]
        iterable $tools,
    ) {
        $byName = [];
        foreach ($tools as $tool) {
            $byName[$tool->name()] = $tool;
        }
        $this->tools = $byName;
    }

    public function reply(string $systemPrompt, array $history): ChatReply
    {
        $messages = array_map(static fn (ChatMessage $m): array => $m->toApi(), $history);
        $toolCalls = [];
        $inputTokens = 0;
        $outputTokens = 0;

        // TODO laag 4, stap 1: prompt caching.
        // Geef cacheControl: CacheControlEphemeral::with() mee aan de call hieronder, en tel per ronde
        // $message->usage->cacheReadInputTokens en cacheCreationInputTokens op (beide nullable), zodat ze
        // via ChatReply in de UI terechtkomen.

        // De lus: zolang het model een tool wil, voeren wíj die uit en sturen we het resultaat terug.
        for ($round = 0;; ++$round) {
            $message = $this->client->messages->create(
                maxTokens: self::MAX_TOKENS,
                messages: $messages,
                model: $this->model,
                system: $systemPrompt,
                tools: $this->toolDefinitions(),
            );
            $inputTokens += $message->usage->inputTokens;
            $outputTokens += $message->usage->outputTokens;

            if ('tool_use' !== $message->stopReason) {
                break;
            }
            if ($round >= self::MAX_TOOL_ROUNDS) {
                throw new \RuntimeException('Het model bleef tools aanvragen, lus afgebroken.');
            }

            $results = [];
            foreach ($message->content as $block) {
                if (!$block instanceof ToolUseBlock) {
                    continue;
                }
                $tool = $this->tools[$block->name] ?? throw new \LogicException("Onbekende tool: {$block->name}");
                $result = $tool->call($block->input);
                $toolCalls[] = new ToolCall($block->name, $block->input, $result);
                $results[] = ToolResultBlockParam::with(toolUseID: $block->id, content: $result);
            }

            // De volledige assistant-beurt (incl. tool_use- en thinking-blokken) én alle resultaten gaan samen terug.
            $messages[] = MessageParam::with(
                content: array_map(self::toParam(...), $message->content),
                role: 'assistant',
            );
            $messages[] = MessageParam::with(content: $results, role: 'user');
        }

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
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            toolCalls: $toolCalls,
        );
    }

    /**
     * Een blok uit het antwoord omzetten naar de vorm waarin we het terugsturen. Thinking-blokken gaan
     * ongewijzigd mee (de signature is verplicht), anders verliest het model zijn redenering van deze beurt.
     */
    private static function toParam(
        mixed $block,
    ): TextBlockParam|ToolUseBlockParam|ThinkingBlockParam|RedactedThinkingBlockParam {
        return match (true) {
            $block instanceof TextBlock => TextBlockParam::with(text: $block->text),
            $block instanceof ToolUseBlock => ToolUseBlockParam::with(
                id: $block->id,
                input: $block->input,
                name: $block->name,
            ),
            $block instanceof ThinkingBlock => ThinkingBlockParam::with(
                signature: $block->signature,
                thinking: $block->thinking,
            ),
            $block instanceof RedactedThinkingBlock => RedactedThinkingBlockParam::with(data: $block->data),
            default => throw new \LogicException('Onverwacht bloktype in het antwoord: '.get_debug_type($block)),
        };
    }

    /**
     * @return list<ToolDefinition>
     */
    private function toolDefinitions(): array
    {
        return array_values(array_map(static fn (Tool $tool): ToolDefinition => ToolDefinition::with(
            inputSchema: $tool->inputSchema(),
            name: $tool->name(),
            description: $tool->description(),
        ), $this->tools));
    }
}

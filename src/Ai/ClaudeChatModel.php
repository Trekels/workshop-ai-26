<?php

declare(strict_types=1);

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Messages\RedactedThinkingBlock;
use Anthropic\Messages\RedactedThinkingBlockParam;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextBlockParam;
use Anthropic\Messages\ThinkingBlock;
use Anthropic\Messages\ThinkingBlockParam;
use Anthropic\Messages\Tool as ToolDefinition;
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
     * @param iterable<Tool> $tools alle services die Tool implementeren, dankzij de tag op de interface
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
        // TODO laag 3: maak hier een lus van.
        //
        // 1. Geef `tools: $this->toolDefinitions()` mee aan messages->create().
        // 2. Is $message->stopReason gelijk aan 'tool_use'? Dan wil het model een tool. Voor elke
        //    Anthropic\Messages\ToolUseBlock in $message->content:
        //      - zoek de tool op naam in $this->tools en roep ->call($block->input) aan,
        //      - onthoud de aanroep als ToolCall (voor de UI),
        //      - maak een ToolResultBlockParam::with(toolUseID: $block->id, content: $resultaat).
        // 3. Voeg twee berichten toe aan $messages: de assistant-beurt (alle blokken uit het antwoord,
        //    via self::toParam) en een user-beurt met de tool_result-blokken. Vraag dan opnieuw.
        // 4. Stop als stopReason iets anders is dan 'tool_use'. Tel de tokens van alle rondes op en
        //    geef de ToolCalls mee in de ChatReply. Begrens de lus met MAX_TOOL_ROUNDS.
        $messages = array_map(static fn (ChatMessage $m): array => $m->toApi(), $history);

        $message = $this->client->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: $messages,
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

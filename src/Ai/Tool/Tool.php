<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Een tool is een stuk code van ons dat het model mag "aanvragen". Het model voert nooit zelf iets uit:
 * het stuurt een naam en input terug, wij beslissen wat daarmee gebeurt en sturen het resultaat.
 */
#[AutoconfigureTag('app.ai_tool')]
interface Tool
{
    public function name(): string;

    /**
     * De beschrijving is wat het model leest om te beslissen wanneer het de tool gebruikt. Wees concreet.
     */
    public function description(): string;

    /**
     * JSON Schema van de input die het model mag meegeven.
     *
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>}
     */
    public function inputSchema(): array;

    /**
     * @param array<string, mixed> $input precies wat het model stuurde, nooit blind vertrouwen
     *
     * @return string het resultaat dat terug naar het model gaat
     */
    public function call(array $input): string;
}

<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Entity\Game;
use App\Repository\GameRepository;

final class SearchGamesTool implements Tool
{
    public function __construct(
        private readonly GameRepository $games,
    ) {
    }

    public function name(): string
    {
        return 'search_games';
    }

    public function description(): string
    {
        return 'Zoekt games in onze bibliotheek. Gebruik dit voor elke vraag over welke games er zijn, '
            .'wat ze kosten, voor hoeveel spelers ze zijn of op welk platform ze draaien. Verzin nooit zelf games.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'genre' => [
                    'type' => 'string',
                    'description' => 'Genre, bv. party, shooter, rpg, puzzle, racing, horror, sandbox',
                ],
                'players' => [
                    'type' => 'integer',
                    'description' => 'Aantal spelers dat tegelijk moet kunnen meespelen',
                ],
                'max_price' => ['type' => 'number', 'description' => 'Maximumprijs in euro'],
                'coop' => [
                    'type' => 'boolean',
                    'description' => 'Enkel games waarin je samenwerkt in plaats van tegen elkaar speelt',
                ],
                'query' => ['type' => 'string', 'description' => 'Vrije zoekterm in titel of beschrijving'],
            ],
            'required' => [],
        ];
    }

    public function call(array $input): string
    {
        // TODO laag 3: vertaal $input naar $this->games->search(...) en geef het resultaat als JSON terug.
        // - De sleutels staan in inputSchema(); alles is optioneel en komt rechtstreeks van het model,
        //   dus cast elke waarde (string, int, float, bool) in plaats van ze blind door te geven.
        // - Geen resultaten? Zeg dat dan in woorden, zodat het model niets hoeft te verzinnen.
        // - Tip: self::describe($game) maakt van een Game een compacte array voor json_encode().
        throw new \LogicException('Laag 3: implementeer SearchGamesTool::call()');
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(Game $game): array
    {
        return [
            'title' => $game->getTitle(),
            'genre' => $game->getGenre(),
            'players' => $game->getMinPlayers().'-'.$game->getMaxPlayers(),
            'price_eur' => $game->getPrice(),
            'platforms' => $game->getPlatforms(),
            'coop' => $game->isCoop(),
            'description' => $game->getDescription(),
        ];
    }
}

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
        // Input van het model is net zo ongetrust als een formulier van een gebruiker: casten en begrenzen.
        $games = $this->games->search(
            genre: isset($input['genre']) ? (string) $input['genre'] : null,
            players: isset($input['players']) ? (int) $input['players'] : null,
            maxPrice: isset($input['max_price']) ? (float) $input['max_price'] : null,
            coop: isset($input['coop']) ? (bool) $input['coop'] : null,
            query: isset($input['query']) ? (string) $input['query'] : null,
        );

        if ([] === $games) {
            return 'Geen games gevonden die aan deze criteria voldoen.';
        }

        return json_encode(array_map(self::describe(...), $games), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
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

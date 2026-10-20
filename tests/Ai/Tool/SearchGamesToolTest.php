<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Ai\Tool\SearchGamesTool;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SearchGamesToolTest extends KernelTestCase
{
    private SearchGamesTool $tool;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Game('Rocket League', 'sport', 1, 4, 0.0, 'PC', true, 'Voetbal met auto\'s.'));
        $entityManager->persist(new Game('Among Us', 'party', 4, 15, 4.49, 'PC', false, 'Zoek de bedrieger.'));
        $entityManager->persist(new Game('Elden Ring', 'rpg', 1, 1, 59.99, 'PC', false, 'Afzien in een open wereld.'));
        $entityManager->flush();

        $this->tool = static::getContainer()->get(SearchGamesTool::class);
    }

    public function testDescribesItselfForTheModel(): void
    {
        self::assertSame('search_games', $this->tool->name());
        self::assertArrayHasKey('properties', $this->tool->inputSchema());
    }

    public function testFiltersOnPriceAndPlayers(): void
    {
        $result = $this->search(['max_price' => 10, 'players' => 4]);

        self::assertSame(['Rocket League', 'Among Us'], array_column($result, 'title'));
    }

    public function testFiltersOnCoopAndGenre(): void
    {
        $result = $this->search(['coop' => true]);
        self::assertSame(['Rocket League'], array_column($result, 'title'));

        $result = $this->search(['genre' => 'RPG']);
        self::assertSame(['Elden Ring'], array_column($result, 'title'));
    }

    public function testFreeTextSearchesTitleAndDescription(): void
    {
        $result = $this->search(['query' => 'bedrieger']);

        self::assertSame(['Among Us'], array_column($result, 'title'));
    }

    public function testTellsTheModelWhenNothingMatches(): void
    {
        $answer = $this->tool->call(['max_price' => 0.01, 'genre' => 'rpg']);

        self::assertStringContainsString('Geen games gevonden', $answer);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<array<string, mixed>>
     */
    private function search(array $input): array
    {
        /** @var list<array<string, mixed>> $result */
        $result = json_decode($this->tool->call($input), true, flags: \JSON_THROW_ON_ERROR);

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Ai\ChatMessage;
use App\Command\PurgeConversationsCommand;
use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeConversationsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        // Twee gesprekken van vandaag, elk met een vraag erin.
        foreach (['Iets gratis?', 'Iets voor twee?'] as $question) {
            $conversation = new Conversation();
            Message::fromChatMessage($conversation, ChatMessage::user($question));
            $this->entityManager->persist($conversation);
        }
        $this->entityManager->flush();
    }

    public function testKeepsConversationsWithinTheRetentionPeriod(): void
    {
        $output = $this->purge(new MockClock('now'), retentionDays: 30);

        self::assertStringContainsString('0 gesprekken gewist', $output);
        self::assertCount(2, $this->entityManager->getRepository(Conversation::class)->findAll());
    }

    public function testDeletesConversationsOlderThanTheRetentionPeriod(): void
    {
        // De klok 31 dagen vooruit: alles van vandaag is nu te oud.
        $output = $this->purge(new MockClock('+31 days'), retentionDays: 30);

        self::assertStringContainsString('2 gesprekken gewist', $output);
        self::assertCount(0, $this->entityManager->getRepository(Conversation::class)->findAll());
        self::assertCount(0, $this->entityManager->getRepository(Message::class)->findAll(), 'berichten gaan mee');
    }

    private function purge(MockClock $clock, int $retentionDays): string
    {
        $command = new PurgeConversationsCommand($this->entityManager, $clock, $retentionDays);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:conversations:purge'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->entityManager->clear();

        return $tester->getDisplay();
    }
}

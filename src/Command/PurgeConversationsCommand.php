<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Conversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bewaartermijn (opslagbeperking, AVG art. 5): gesprekken die niemand nog nodig heeft, wissen we zelf.
 * Draai dit dagelijks via cron of Symfony Scheduler: `php bin/console app:conversations:purge`.
 */
#[AsCommand(name: 'app:conversations:purge', description: 'Wist gesprekken die ouder zijn dan de bewaartermijn')]
final class PurgeConversationsCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'int:CHAT_RETENTION_DAYS')]
        private readonly int $retentionDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        // De klok injecteren in plaats van new \DateTimeImmutable(): zo kan de test "31 dagen later" simuleren.
        $cutoff = $this->clock->now()->modify(sprintf('-%d days', $this->retentionDays));

        /** @var list<Conversation> $expired */
        $expired = $this->entityManager->createQuery(
            'SELECT c FROM App\\Entity\\Conversation c WHERE c.createdAt < :cutoff',
        )->setParameter('cutoff', $cutoff)->getResult();

        foreach ($expired as $conversation) {
            $this->entityManager->remove($conversation);
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d gesprekken gewist (ouder dan %d dagen).', \count($expired), $this->retentionDays));

        return Command::SUCCESS;
    }
}

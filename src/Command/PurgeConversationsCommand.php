<?php

declare(strict_types=1);

namespace App\Command;

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
        // TODO laag 6: bereken de grensdatum (nu min de bewaartermijn, via $this->clock zodat de test de tijd kan
        // verzetten), zoek de gesprekken met een createdAt ouder dan die grens, verwijder ze (Doctrine neemt de
        // berichten mee) met één flush, en meld hoeveel er weg zijn. De test in tests/Command/ zegt of het klopt.
        throw new \LogicException('Nog niet geïmplementeerd: zie README, opdracht laag 6.');

        return Command::SUCCESS;
    }
}

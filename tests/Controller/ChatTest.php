<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Ai\ChatMessage;
use App\Ai\ChatModel;
use App\Ai\Role;
use App\Ai\ToolCall;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Tests\Fake\FakeChatModel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChatTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakeChatModel $model;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->model = new FakeChatModel('Hallo, ik ben de fake.');
        static::getContainer()->set(ChatModel::class, $this->model);

        // Verse, lege SQLite-database per test.
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testEmptyChatPage(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.chat__empty');
    }

    public function testAskingAQuestionShowsQuestionAndAnswer(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Wat is Symfony?']);

        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.message--user', 'Wat is Symfony?');
        self::assertSelectorTextContains('.message--assistant', 'Hallo, ik ben de fake.');
        self::assertSelectorTextContains('.message__meta', 'in: 12 tokens');
    }

    public function testFullHistoryIsSentOnEveryCall(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Eerste vraag']);
        $this->client->followRedirect();
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Tweede vraag']);

        self::assertCount(2, $this->model->receivedHistories);

        $secondCall = $this->model->receivedHistories[1];
        self::assertCount(3, $secondCall, 'vraag 1, antwoord 1 en vraag 2 gaan allemaal mee');
        self::assertSame(Role::User, $secondCall[0]->role);
        self::assertSame('Eerste vraag', $secondCall[0]->content);
        self::assertSame(Role::Assistant, $secondCall[1]->role);
        self::assertSame('Hallo, ik ben de fake.', $secondCall[1]->content);
        self::assertSame('Tweede vraag', $secondCall[2]->content);
    }

    public function testToolCallsAreShownWithTheAnswer(): void
    {
        $this->model->toolCalls = [new ToolCall('search_games', ['genre' => 'party'], '[{"title":"Among Us"}]')];

        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Iets voor een feestje?']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.toolcall summary', 'search_games({"genre":"party"})');
        self::assertSelectorTextContains('.toolcall pre', 'Among Us');
    }

    public function testCacheUsageIsShownWithTheAnswer(): void
    {
        $this->model->inputTokens = 40;
        $this->model->cacheReadTokens = 1500;

        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Nog iets?']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.message__meta', 'cache: 1500 gelezen');
        self::assertSelectorTextContains('.chat__context', '1540 tokens');
    }

    public function testLongConversationIsCompactedIntoASummary(): void
    {
        // De prompt is groter dan de drempel (ook de gecachte tokens tellen mee), dus na het derde
        // antwoord worden de oudste berichten samengevat.
        $this->model->inputTokens = 100;
        $this->model->cacheReadTokens = 999999;
        $this->model->answerWith('Antwoord 1', 'Antwoord 2', 'Antwoord 3', 'SAMENVATTING: zoekt coop');

        $this->client->request('GET', '/');
        foreach (['Vraag 1', 'Vraag 2', 'Vraag 3'] as $question) {
            $this->client->submitForm('Verstuur', ['chat_input[message]' => $question]);
            $this->client->followRedirect();
        }

        // Drie gewone calls plus één samenvattingscall.
        self::assertCount(4, $this->model->receivedHistories);
        $summaryCall = $this->model->receivedHistories[3];
        self::assertSame('Vraag 1', $summaryCall[0]->content, 'enkel het oudste paar wordt samengevat');
        self::assertSame('Antwoord 1', $summaryCall[1]->content);
        self::assertSame(Role::User, $summaryCall[2]->role, 'afgesloten met de vraag om samen te vatten');

        self::assertSelectorTextContains('.summary', 'SAMENVATTING: zoekt coop');
        self::assertSelectorTextNotContains('.chat__messages', 'Vraag 1');
        self::assertSelectorTextContains('.chat__messages', 'Vraag 2');
        self::assertCount(6, $this->entityManager->getRepository(Message::class)->findAll(), 'niets gewist');

        // De volgende vraag krijgt de samenvatting in de system prompt en enkel de recente berichten mee.
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Vraag 4']);

        $lastCall = $this->model->receivedHistories[4];
        self::assertStringContainsString('SAMENVATTING: zoekt coop', $this->model->receivedSystemPrompts[4]);
        self::assertSame(
            ['Vraag 2', 'Antwoord 2', 'Vraag 3', 'Antwoord 3', 'Vraag 4'],
            array_column($lastCall, 'content'),
        );
    }

    public function testShortConversationIsNotCompacted(): void
    {
        $this->model->cacheReadTokens = 999999;

        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Vraag 1']);
        $this->client->followRedirect();
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Vraag 2']);
        $this->client->followRedirect();

        self::assertCount(2, $this->model->receivedHistories, 'te weinig berichten om samen te vatten');
        self::assertSelectorNotExists('.summary');
    }

    public function testPersonalDataIsMaskedBeforeItLeavesTheServer(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', [
            'chat_input[message]' => 'Ik ben jan@example.com, bel me op 0470 12 34 56. Iets voor 2 spelers?',
        ]);
        $this->client->followRedirect();

        // Het model krijgt de gemaskeerde versie, en onze database ook: wat je niet bewaart, kan niet lekken.
        $sent = $this->model->receivedHistories[0][0]->content;
        self::assertSame('Ik ben [e-mail], bel me op [telefoon]. Iets voor 2 spelers?', $sent);
        self::assertSelectorTextContains('.message--user', '[e-mail]');
        self::assertSelectorTextNotContains('.message--user', 'jan@example.com');
    }

    public function testDeletingTheConversationRemovesOnlyMyRowsFromTheDatabase(): void
    {
        // Een gesprek van iemand anders, dat moet blijven staan.
        $other = new Conversation();
        Message::fromChatMessage($other, ChatMessage::user('Vraag van een ander'));
        $this->entityManager->persist($other);
        $this->entityManager->flush();

        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Mijn vraag']);
        $this->client->followRedirect();
        $this->client->submitForm('Wis mijn gesprek');

        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.chat__empty');
        self::assertSelectorTextContains('.flash--info', 'gewist');

        $this->entityManager->clear();
        $conversations = $this->entityManager->getRepository(Conversation::class)->findAll();
        self::assertCount(1, $conversations, 'enkel het mijne weg');
        $messages = $this->entityManager->getRepository(Message::class)->findAll();
        self::assertSame(
            ['Vraag van een ander'],
            array_map(static fn (Message $m): string => $m->getContent(), $messages),
        );

        // En na het wissen gewoon verder kunnen: een nieuw gesprek start leeg.
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Nieuwe vraag']);
        self::assertCount(1, $this->model->receivedHistories[1], 'geen geschiedenis meer');
    }

    public function testTransparencyNoticeTellsWhatHappensWithTheData(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.chat__notice', 'Anthropic');
        self::assertSelectorTextContains('.chat__notice', 'maskeren');
        self::assertSelectorTextContains('.chat__notice', 'dagen');
    }

    public function testBlankQuestionIsRejected(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.chat__form', 'Typ eerst een vraag.');
        self::assertCount(0, $this->model->receivedHistories);
    }

    public function testNewConversationStartsEmptyButKeepsOldMessagesInDatabase(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Verstuur', ['chat_input[message]' => 'Vraag']);
        $this->client->followRedirect();
        $this->client->submitForm('Nieuw gesprek');

        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.chat__empty');

        // "Nieuw gesprek" vergeet enkel de sessie. De data blijft staan, daar komen we in laag 6 op terug.
        self::assertCount(2, $this->entityManager->getRepository(Message::class)->findAll());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Ai\ChatModel;
use App\Ai\Role;
use App\Ai\ToolCall;
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

# Workshop AI – Symfony + Claude

Seminarie-project: een chatapp die laag per laag slimmer en daarna veiliger wordt.
`main` is het startpunt dat je cloont. Elke laag heeft een eigen branch met de oplossing,
en een tag `layer-N-start` met het vertrekpunt van de opdracht (`git checkout layer-2-start`).

| Laag | Branch | Onderwerp |
|------|--------|-----------|
| 1 | `layer-1-chat` | Chat: één vraag, één antwoord, geen geheugen |
| 2 | `layer-2-memory` | Geheugen: geschiedenis meesturen, gesprekken in de database, memory tool |
| 3 | `layer-3-tools` | Tools: het model zoekt zelf in onze gamebibliotheek |
| 4 | `layer-4-compression` | Tokens, caching, compaction |
| 5 | `layer-5-data-safety` | Prompt injection, logging, wat verlaat je server |
| 6 | `layer-6-gdpr` | Wissen, bewaartermijn, transparantie |

## Zelf draaien

Vereist: PHP 8.4+ met de extensie `pdo_sqlite`, Composer, en een Anthropic API key
(krijg je tijdens het seminarie).

```bash
composer install
cp .env.dist .env           # Windows: copy .env.dist .env   — en vul ANTHROPIC_API_KEY in
php bin/console doctrine:migrations:migrate -n   # SQLite in var/, met de tabellen en 16 games voor alle lagen
php -S 127.0.0.1:8000 -t public                  # of: symfony serve -d
```

Open daarna http://127.0.0.1:8000. De database is SQLite en wordt automatisch aangemaakt in `var/`.

### Windows met Laragon

1. **PHP 8.4.** Laragon levert die niet standaard mee: download de "Thread Safe" zip op
   https://windows.php.net/download/, pak hem uit in `C:\laragon\bin\php\php-8.4.x\` en
   kies die versie via Menu → PHP → Version.
2. **Extensies.** Open Menu → PHP → php.ini en zet deze regels aan (de `;` ervoor weg):
   `extension=pdo_sqlite`, `extension=sqlite3`, `extension=openssl`, `extension=curl`,
   `extension=mbstring`, `extension=fileinfo`. Daarna Laragon herstarten.
3. **Project.** Clone in `C:\laragon\www\workshop-ai-26` en doorloop de stappen hierboven in
   de Laragon-terminal (Cmder). Laragon maakt automatisch de vhost http://workshop-ai-26.test
   aan die naar `public/` wijst, de `php -S`-stap mag je dan overslaan. Apache gebruikt
   `public/.htaccess` voor de routing; kies je nginx, dan werkt dat zonder extra config.

Werkt de vhost niet, dan is `php -S 127.0.0.1:8000 -t public` altijd het alternatief.

### Model wisselen

Het model is een env-variabele. Zet in `.env` bijvoorbeeld:

```
CLAUDE_MODEL=claude-sonnet-5-5
```

Beschikbare modellen en prijzen: https://docs.anthropic.com/en/docs/about-claude/models

## Opdracht laag 1: laat de app praten

Op `main` werkt alles behalve de call naar Claude. Stuur een vraag en je krijgt een
`LogicException` die zegt wat er ontbreekt. Je doel: de app antwoordt.

Open `src/Ai/ClaudeChatModel.php` en implementeer `reply()`. De stappen staan als
commentaar in de methode. Testen doe je met een echte key: `ANTHROPIC_API_KEY` in `.env` invullen,
de app starten en een vraag stellen. De tokens onder het antwoord zijn je eerste
kennismaking met wat een call kost.

Klaar? Verwijder dan ook het `ignoreErrors`-blok onderaan `phpstan.dist.neon`, dat
bestond alleen voor de lege stub.

## Opdracht laag 2: geef de chat een geheugen

Vertrekpunt: `git checkout layer-2-start`. De entities `Conversation` en `Message` staan al klaar
sinds `main`, de UI toont het hele gesprek en heeft een knop "Nieuw gesprek".
Maar stuur een vraag en je krijgt weer een `LogicException`. Je doel: een gesprek dat
over meerdere vragen heen blijft lopen, en `composer test` groen (3 van de 5 tests zijn rood).

1. `src/Chat/DoctrineConversationStore.php`: het geheugen zelf. `messages()`, `append()` en
   `clear()`. De sessie onthoudt enkel het id van het actieve gesprek, de berichten staan in SQLite.
2. `src/Chat/ChatService.php` → `ask()`: het model is stateless. Het "herinnert" zich niets,
   dus jij stuurt de volledige geschiedenis mee bij elke call. Kijk daarna naar de input-tokens
   onder elk antwoord: die groeien met elke beurt. Daar gaat laag 4 over.

Klaar? Verwijder het `ignoreErrors`-blok onderaan `phpstan.dist.neon`.

## Opdracht laag 3: geef het model een tool

Vertrekpunt: `git checkout layer-3-start`. De tabel `game` met 16 games zit al in de database sinds
`main`. Vraag "iets gratis voor vier man?" en het model kan enkel gokken: het kent onze
bibliotheek niet. Je doel: het model zoekt zélf in de database, en je ziet elke tool-call in de UI.

1. `src/Ai/Tool/SearchGamesTool.php` → `call()`: de tool zelf. Het model stuurt een input-array
   (zie `inputSchema()`), jij vertaalt die naar `GameRepository::search()` en geeft JSON terug.
   De tests in `tests/Ai/Tool/SearchGamesToolTest.php` vertellen je wanneer het klopt.
2. `src/Ai/ClaudeChatModel.php` → `reply()`: de lus. Stuur de tooldefinities mee, en zolang
   `stopReason` gelijk is aan `tool_use`: voer elke `ToolUseBlock` uit, stuur de assistant-beurt
   plus de `tool_result`-blokken terug, en vraag opnieuw. Het model voert nooit zelf iets uit.

Het model beslist zelf wanneer het de tool gebruikt op basis van `description()`. Pas die
beschrijving eens aan en kijk wat er gebeurt. Wie klaar is: de SDK heeft ook
`$client->beta->messages->toolRunner()`, die precies deze lus voor je doet.

Klaar? Verwijder het `ignoreErrors`-blok onderaan `phpstan.dist.neon`.

## Opdracht laag 4: hou de context klein (en goedkoop)

Vertrekpunt: `git checkout layer-4-start`. Stel een paar vragen na elkaar en kijk naar "Context bij het
laatste antwoord": elke vraag stuurt de volledige geschiedenis mee, dus de prompt groeit en groeit.
Twee remedies, van goedkoop naar ingrijpend.

1. **Prompt caching** in `src/Ai/ClaudeChatModel.php` → `reply()`: geef `cacheControl` mee aan de call.
   De API cachet dan het stuk van de prompt dat niet verandert (tools, system prompt, oudere berichten)
   en rekent dat bij de volgende vraag aan 10% van de prijs aan. Lees `cacheReadInputTokens` en
   `cacheCreationInputTokens` uit `usage` en geef ze door via `ChatReply`: vanaf nu zie je in de UI
   wat uit de cache kwam. Let op: `inputTokens` is dan enkel nog het ongecachte stuk, vandaar
   `ChatReply::promptTokens()`.
2. **Compaction** in `src/Chat/HistoryCompactor.php` → `compactIfNeeded()`: komt de prompt boven
   `CHAT_COMPACT_AFTER_TOKENS` (zie `.env.dist`), laat het model dan de oudste berichten samenvatten en
   stuur daarna enkel nog die samenvatting plus de laatste vier berichten mee. `ConversationStore::compact()`
   en de UI (het 📦-blokje) staan klaar. De test `testLongConversationIsCompactedIntoASummary` zegt
   wanneer het klopt. Zet de drempel laag (bv. 1500) om het live te zien gebeuren.

Caching maakt de prompt niet kleiner, enkel goedkoper, en een samenvatting is nooit gratis: het is
een extra call, en het model vergeet details. Kijk na een compaction eens wat er nog in de samenvatting
staat en wat niet. De API kan dit ook server-side (compaction en context editing, beide beta); de
logica is dezelfde, maar dan zie je ze niet meer.

Klaar? Verwijder het `ignoreErrors`-blok onderaan `phpstan.dist.neon`.

## Opdracht laag 5: wat verlaat je server, en wat komt er binnen

Vertrekpunt: `git checkout layer-5-start`, en daarna opnieuw `php bin/console doctrine:migrations:migrate -n`.
Die migratie verandert de beschrijving van één game: er staat nu een "tip" met een link in, en een valse bewering
dat Rocket League en Valorant niet meer gratis zijn. Zet `CLAUDE_MODEL=claude-haiku-4-5-20251001` in `.env`,
vraag "iets gratis voor vier man?" en kijk wat het model doet. Probeer daarna hetzelfde met Opus.

Open `var/log/ai.log`: sinds deze laag logt `src/Ai/LoggingChatModel.php` (een `#[AsDecorator]` rond
`ChatModel`) alles wat naar Anthropic vertrekt en terugkomt. Zoek het tool-resultaat en de beschrijving van
Fall Guys. Dat is prompt injection: tekst uit je eigen database die jij niet schreef, die via een tool in de
prompt belandt, en die het model leest alsof ze even betrouwbaar is als de prijs ernaast. Haiku geeft de link
gewoon door als "pro-tip". Opus doorziet de truc, maar gaat wel aan zijn eigen prijsdata twijfelen en vraagt
je "het even te checken". Een groter model helpt, maar daar bouw je niet op.

1. **Data is geen instructie, en geen waarheid.** De goedkoopste verdediging staat in `src/Chat/ChatService.php`:
   zeg in de system prompt dat tool-resultaten data zijn, dat vrije tekst erin door iedereen geschreven kan zijn,
   dat de velden (`price_eur`, `players`, `coop`) de waarheid zijn, en dat links uit beschrijvingen nooit
   doorgegeven worden. Stel de vraag opnieuw, op Haiku en op Opus. Dit helpt, maar het blijft een verzoek aan
   het model. Daarom is de échte verdediging dat `search_games` enkel kan lezen: het ergste wat hier kan gebeuren
   is een slecht advies. Geef het model nooit een tool met gevolgen (mail sturen, bestellen, wissen) zonder dat
   een mens bevestigt.
2. **Stuur niet meer dan nodig.** Typ "Ik ben jan@example.com, bel me op 0470 12 34 56, iets voor 2 spelers?"
   en lees `ai.log`: je e-mailadres en nummer vertrokken naar een externe partij en staan in onze database,
   terwijl het model ze niet nodig heeft om een game te kiezen. Implementeer `src/Chat/PiiRedactor.php` →
   `redact()`: e-mailadressen en telefoonnummers maskeren vóór de vraag de server verlaat. `ChatService`
   roept hem al aan. De tests in `tests/Chat/PiiRedactorTest.php` zeggen wat je minstens moet herkennen
   en wat je niet mag kapotmaken.

Data-minimalisatie is geen beleefdheid maar een GDPR-principe, en het is de brug naar laag 6: wat je
niet verstuurt, hoef je ook niet te beschermen, te bewaren of te wissen.

## Opdracht laag 6: de data heeft een eigenaar

Vertrekpunt: `git checkout layer-6-start`. Sinds laag 2 laat "Nieuw gesprek" de rijen bewust staan, en sinds
laag 4 staat er ook een samenvatting van je gesprek in de database. Dat is persoonsdata, ook zonder naam erin.
De AVG vraagt drie dingen die je hier concreet bouwt: de gebruiker mag wissen, wij bewaren niet eeuwig, en we
zeggen wat we doen. Het derde staat al: lees de regel onder het invoerveld. Die komt uit `templates/chat/index.html.twig`
met twee Twig-globals uit `config/packages/twig.yaml`.

1. **Recht op wissen.** De knop "Wis mijn gesprek" en `DeleteController` bestaan al, maar
   `DoctrineConversationStore::delete()` gooit nog een `LogicException`. Implementeer ze: het actieve gesprek
   verwijderen (de berichten gaan mee via de cascade op `Conversation::$messages`), flushen, sessie vergeten.
   Enkel het eigen gesprek: `testDeletingTheConversationRemovesOnlyMyRowsFromTheDatabase` plant een gesprek van
   iemand anders en controleert dat dat blijft staan.
2. **Bewaartermijn.** `src/Command/PurgeConversationsCommand.php` → `__invoke()`: gesprekken ouder dan
   `CHAT_RETENTION_DAYS` (zie `.env.dist`) wissen. Gebruik `$this->clock` voor "nu", niet `new \DateTimeImmutable()`:
   de test in `tests/Command/` zet de klok 31 dagen vooruit om te zien of het werkt. Draai hem daarna zelf:
   `php bin/console app:conversations:purge`. In productie hangt dit aan cron of Symfony Scheduler.

Klaar? Verwijder het `ignoreErrors`-blok onderaan `phpstan.dist.neon`.

Wat je niet gebouwd hebt, maar wel moet weten: `var/log/ai.log` uit laag 5 bevat dezelfde gesprekken en valt onder
dezelfde bewaartermijn (rotating logs, of niet loggen wat je niet nodig hebt). En de data die naar Anthropic
vertrok, wis jij niet; wat daar mee gebeurt, staat in hun verwerkersovereenkomst. Daarom was laag 5 de belangrijkste
GDPR-laag: wat je niet verstuurt, hoef je nergens te laten wissen.

## Kwaliteitschecks

```bash
composer csfixer   # code style (php-cs-fixer, Symfony-regels)
composer phpstan   # statische analyse
composer test      # phpunit
```

## Waar zit wat

- `src/Ai/` – het gesprek met het model: `ChatModel` (interface), `ClaudeChatModel` (de SDK-call)
  en `LoggingChatModel` (schrijft elke call naar `var/log/ai.log`)
- `src/Chat/` – `ChatService` (system prompt, geschiedenis meesturen), `ConversationStore` (het geheugen, in SQLite),
  `HistoryCompactor` (samenvatten als de context te groot wordt) en `PiiRedactor` (persoonsgegevens maskeren)
- `src/Ai/Tool/` – `Tool` (interface) en `SearchGamesTool`: code die het model mag aanvragen
- `src/Entity/` + `migrations/` – `Conversation`, `Message` en `Game`: de database voor álle lagen staat al klaar,
  ORM-mapping typen is niet de les. Vanaf laag 2 gebruiken we ze; `src/Ai/ChatMessage.php` en `ToolCall.php` ook
- `src/Controller/Chat/` – één controller per route (`Index`, `Send`, `Reset`, `Delete`)
- `src/Command/PurgeConversationsCommand.php` – de bewaartermijn, voor cron of Scheduler
- `templates/chat/` – de UI, met per antwoord het tokenverbruik (en wat uit de cache kwam)
- `tests/` – `FakeChatModel` vervangt Claude, zodat tests geen key of netwerk nodig hebben

## Voor de docent: branches en stacked PR's

- `main` = startpunt voor studenten, met de TODO-stubs.
- `layer-1-chat` vertrekt van `main` en bevat de oplossing van de opdracht.
- Elke volgende laag vertrekt van de vorige: `layer-2-memory` van `layer-1-chat`, enzovoort.
- Open per laag een PR naar de vorige branch (stacked). De PR-diff toont dan precies
  wat die laag toevoegt, handig om tijdens het seminarie door te lopen.

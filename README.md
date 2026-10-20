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

## Kwaliteitschecks

```bash
composer csfixer   # code style (php-cs-fixer, Symfony-regels)
composer phpstan   # statische analyse
composer test      # phpunit
```

## Waar zit wat

- `src/Ai/` – het gesprek met het model: `ChatModel` (interface), `ClaudeChatModel` (de SDK-call)
- `src/Chat/` – `ChatService` (system prompt, geschiedenis meesturen) en `ConversationStore` (het geheugen, in SQLite)
- `src/Ai/Tool/` – `Tool` (interface) en `SearchGamesTool`: code die het model mag aanvragen
- `src/Entity/` + `migrations/` – `Conversation`, `Message` en `Game`: de database voor álle lagen staat al klaar,
  ORM-mapping typen is niet de les. Vanaf laag 2 gebruiken we ze; `src/Ai/ChatMessage.php` en `ToolCall.php` ook
- `src/Controller/Chat/` – één controller per route (`Index`, `Send`, `Reset`)
- `templates/chat/` – de UI, met per antwoord het tokenverbruik
- `tests/` – `FakeChatModel` vervangt Claude, zodat tests geen key of netwerk nodig hebben

## Voor de docent: branches en stacked PR's

- `main` = startpunt voor studenten, met de TODO-stubs.
- `layer-1-chat` vertrekt van `main` en bevat de oplossing van de opdracht.
- Elke volgende laag vertrekt van de vorige: `layer-2-memory` van `layer-1-chat`, enzovoort.
- Open per laag een PR naar de vorige branch (stacked). De PR-diff toont dan precies
  wat die laag toevoegt, handig om tijdens het seminarie door te lopen.

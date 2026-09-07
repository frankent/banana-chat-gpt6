# Banana Chat

Banana Chat is a self-hosted workspace messenger built from [PRODUCT_SPEC.md](./PRODUCT_SPEC.md). The repository is a pnpm/Turborepo monorepo with a Laravel API and admin panel, React web client, Expo mobile client, and shared TypeScript packages.

## What is implemented

- Opaque access tokens, rotating refresh tokens, reuse detection, session revocation, forced password changes, account lockout, and Argon2id passwords.
- Strict workspace context and room membership checks, DM/group creation, member roles, ownership transfer, leave/delete/hide/pin flows, and audit records.
- Ordered/idempotent messages, replies, mentions, edits, tombstones, read cursors, exact unread counts, Reverb events, optional EMQX mirroring, and REST catch-up.
- Direct S3-compatible uploads, MIME validation, image thumbnails, video posters, signed downloads, quota enforcement, orphan cleanup, and retention jobs.
- Responsive English/Thai web UI with workspace/room management, media, room search, saved drafts, IndexedDB caching/outbox, service-worker notification handling, AI, and accessible dialogs.
- Private AI conversations with consent, OpenAI-compatible providers, quota accounting, generation events, compaction, explicit memory, regenerate/edit, search, and share-to-room.
- Filament resources for users, workspaces, memberships, rooms, storage, settings, audit logs, and AI providers.
- Expo mobile login, workspace switching, cached rooms/history, attachment rendering, secure offline outbox, message sending, and periodic catch-up.
- PostgreSQL/Redis/S3 production configuration, API and web containers, Reverb plus optional EMQX publication, CI, runbooks, and a k6 message-load scenario.

Store signing/distribution and provider-specific FCM/APNs credentials are deployment-owner steps because they require Apple, Google, and Expo accounts. The repository includes the device API, Expo delivery path, web service worker, deep-link payload format, and deployment settings needed to connect them.

## Local setup

Requirements: Node 22+, pnpm through Corepack, PHP 8.3+ with SQLite for the simplest local path, and Composer 2. Docker is optional.

```bash
corepack pnpm install
cd apps/api
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
cd ../..
corepack pnpm dev:api
```

In a second terminal:

```bash
corepack pnpm dev
```

Open `http://127.0.0.1:5173`. Demo accounts are `tony`, `mali`, and `beam`; the local seed password is `BananaChat2026!`. `tony` can also sign in at `http://127.0.0.1:8000/admin`.

For PostgreSQL, Redis, MinIO, and Mailpit:

```bash
docker compose -f infra/docker-compose.yml up -d
```

Then copy `apps/api/.env.example`, generate an app key, and run the migrations. Start Reverb and the queue worker in separate terminals:

```bash
cd apps/api
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan horizon
```

The web app falls back to five-second REST catch-up when Reverb variables are absent.

## AI provider

Sign in to the admin panel, open **AI providers**, and create an OpenAI-compatible provider. The API key is encrypted with Laravel's `APP_KEY` and only its last four characters are displayed. Use HTTPS provider URLs; private network hosts require `AI_ALLOW_PRIVATE_HOSTS=true`.

## Verification

```bash
corepack pnpm check
docker build --target base -f infra/Dockerfile.api -t banana-chat-php:dev .
```

`corepack pnpm check` verifies all shared packages, web and mobile TypeScript, unit tests, Laravel feature tests, and the production web bundle. GitHub Actions runs the same check and builds the production API container.

With the seeded API and web development servers running, install Chromium once and run the desktop and mobile-width browser smoke tests:

```bash
corepack pnpm exec playwright install chromium
corepack pnpm test:e2e
```

Run the isolated full-stack regression suite with one command. It creates a temporary SQLite database, starts the API and web app, checks backend health, exercises chat messages and attachments, signs into the admin panel, and saves screenshots, videos, traces, and an HTML report under `artifacts/regression/`:

```bash
corepack pnpm test:regression
```

API routes are described in [openapi.yaml](./openapi.yaml); regenerate TypeScript contract types with `corepack pnpm gen:client`.

Production deployment, backup, restore, rollback, and incident commands are documented in [docs/RUNBOOK.md](./docs/RUNBOOK.md).

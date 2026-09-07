# Banana Chat operations runbook

Run commands from the repository root. Keep `apps/api/.env` outside version control and set a unique `APP_KEY`, database password, Redis connection, allowed origins, Reverb credentials, mail settings, and AI encryption/provider settings before the first deployment.

## Deploy

1. Back up PostgreSQL before migrations.
2. Build the immutable API image and start the data services:

   ```bash
   POSTGRES_PASSWORD='<deployment-secret>' docker compose -f infra/docker-compose.prod.yml build api
   POSTGRES_PASSWORD='<deployment-secret>' docker compose -f infra/docker-compose.prod.yml up -d postgres redis
   ```

3. Apply migrations once, then start the API, Reverb, Horizon, scheduler, and Nginx:

   ```bash
   POSTGRES_PASSWORD='<deployment-secret>' docker compose -f infra/docker-compose.prod.yml run --rm api php artisan migrate --force
   POSTGRES_PASSWORD='<deployment-secret>' docker compose -f infra/docker-compose.prod.yml up -d
   ```

4. Verify `GET /api/v1/health`, the admin login page, one API login, and one room history request. Treat a failed database, queue, Redis, or Reverb health check as a failed deployment.

## Back up and restore PostgreSQL

Create a compressed custom-format backup:

```bash
mkdir -p backups
docker compose -f infra/docker-compose.prod.yml exec -T postgres pg_dump -U banana -d banana_chat -Fc > "backups/banana-chat-$(date +%Y%m%d-%H%M%S).dump"
```

Restore into an empty database during a maintenance window:

```bash
docker compose -f infra/docker-compose.prod.yml stop api reverb worker scheduler
docker compose -f infra/docker-compose.prod.yml exec -T postgres dropdb -U banana --if-exists banana_chat
docker compose -f infra/docker-compose.prod.yml exec -T postgres createdb -U banana banana_chat
docker compose -f infra/docker-compose.prod.yml exec -T postgres pg_restore -U banana -d banana_chat --clean --if-exists < backups/<backup-file>.dump
docker compose -f infra/docker-compose.prod.yml up -d
```

Verify the health endpoint and the newest room message after restoration.

## Roll back

Keep the prior source revision and its built image available until the deployment passes smoke checks. If application health fails, check out the known-good revision, rebuild, and start the services again. Restore the pre-deploy database backup when the failed release included a destructive or incompatible migration. Avoid running migration `down` methods against production data without testing the exact downgrade on a restored copy first.

## Incident checks

Use these commands before changing state:

```bash
docker compose -f infra/docker-compose.prod.yml ps
docker compose -f infra/docker-compose.prod.yml logs --since=30m api reverb worker scheduler
docker compose -f infra/docker-compose.prod.yml exec api php artisan horizon:status
docker compose -f infra/docker-compose.prod.yml exec api php artisan queue:failed
```

Rotate a leaked AI provider key in the Filament admin panel and revoke affected chat sessions after an authentication incident. Preserve application, proxy, database, and audit logs for the incident timeline.

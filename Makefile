.PHONY: install dev api mobile check test build generate docker-up docker-down

install:
	corepack pnpm install
	composer install --working-dir=apps/api

dev:
	corepack pnpm dev

api:
	corepack pnpm dev:api

mobile:
	corepack pnpm dev:mobile

check:
	corepack pnpm check

test:
	corepack pnpm test
	corepack pnpm test:api

build:
	corepack pnpm build

generate:
	corepack pnpm gen:client

docker-up:
	docker compose -f infra/docker-compose.yml up -d

docker-down:
	docker compose -f infra/docker-compose.yml down

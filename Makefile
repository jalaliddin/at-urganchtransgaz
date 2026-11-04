# AT.urtg.uz — developer shortcuts. Pass extra arguments with ARGS="...".
DC := docker compose
BACKEND := $(DC) exec -T backend
FRONTEND := $(DC) exec -T frontend

.PHONY: check-limits help init up down build ps logs shell migrate fresh seed test-backend test-frontend lint lint-backend lint-frontend build-frontend check

help: ## List targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  %-16s %s\n", $$1, $$2}'

init: ## Create .env with generated secrets
	./scripts/init-env.sh

up: ## Build and start all services
	$(DC) up -d --build

down: ## Stop services (data volumes are kept)
	$(DC) down

build: ## Build images
	$(DC) build

ps: ## Service status
	$(DC) ps

logs: ## Follow logs (ARGS=service)
	$(DC) logs -f --tail=100 $(ARGS)

shell: ## Shell in the backend container
	$(DC) exec backend sh

migrate: ## Run migrations
	$(BACKEND) php artisan migrate --force

fresh: ## Drop all tables and re-run migrations + seeders (development only)
	$(BACKEND) php artisan migrate:fresh --seed

seed: ## Run seeders (idempotent)
	$(BACKEND) php artisan db:seed --force

test-backend: ## PHPUnit (ARGS="--filter=Name")
	$(BACKEND) php artisan test $(ARGS)

test-frontend: ## Vitest (ARGS="path")
	$(FRONTEND) npm run test -- --run $(ARGS)

check-limits: ## nginx/PHP request limits fit the upload rules
	./scripts/check-upload-limits.sh

lint-backend: ## Pint (check) + Larastan
	$(BACKEND) vendor/bin/pint --test
	$(BACKEND) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

lint-frontend: ## Typecheck + ESLint
	$(FRONTEND) npm run typecheck
	$(FRONTEND) npm run lint

lint: lint-backend lint-frontend ## All linters

build-frontend: ## Production build of the SPA
	$(FRONTEND) npm run build

check: check-limits test-backend lint-backend test-frontend lint-frontend build-frontend ## Every quality gate

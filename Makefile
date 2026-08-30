.PHONY: help up down build fresh shell test lint analyse logs prod-up prod-down install migrate seed

help: ## Show available commands
	@grep -E "^[a-zA-Z_-]+:.*?## .*$$" $(MAKEFILE_LIST) | awk "BEGIN {FS = \":.*?## \"}; {printf \"\033[36m%-20s\033[0m %s\n\", \$$1, \$$2}"

install: ## First-time setup: copy env, build, start, generate key, migrate
	@[ -f .env ] || cp .env.docker .env

	docker compose build
	docker compose up -d
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	@echo "Installation complete! Visit http://localhost"

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Build Docker images
	docker compose build

fresh: ## Rebuild from scratch: destroy, build, start, migrate with seed
	docker compose down -v
	docker compose build
	docker compose up -d
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed

shell: ## Open shell in the app container
	docker compose exec app sh

test: ## Run Laravel test suite
	docker compose exec app php artisan test

lint: ## Check code formatting with Pint and ensure no TLS certs/keys are committed
	@! git ls-files | grep -qE '\.(pem|key|crt)$$' || (echo "ERROR: Committed TLS key/cert files detected in git!" && exit 1)
	docker compose exec app ./vendor/bin/pint --test

analyse: ## Run PHPStan static analysis
	docker compose exec app ./vendor/bin/phpstan analyse

logs: ## Follow container logs
	docker compose logs -f

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

seed: ## Seed the database
	docker compose exec app php artisan db:seed

prod-up: ## Start production environment
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

prod-down: ## Stop production environment
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

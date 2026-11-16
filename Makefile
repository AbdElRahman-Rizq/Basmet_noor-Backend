.PHONY: up down build shell composer artisan test logs fresh

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build app

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(ARGS)

artisan:
	docker compose exec app php artisan $(ARGS)

test:
	docker compose exec app php artisan test

logs:
	docker compose logs -f

# Database rebuilds stay disabled until Gate C (schema/migrations) is approved.
fresh:
	@echo ERROR: 'make fresh' is disabled.
	@echo Gate C has not been approved, so database migrations are not allowed in Phase 0.
	@echo This target will be enabled after the Schema v4 migration plan is approved.
	@exit 1

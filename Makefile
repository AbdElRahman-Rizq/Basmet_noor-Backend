.PHONY: up down build shell composer artisan test logs

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

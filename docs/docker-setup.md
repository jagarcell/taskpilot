# Docker setup

TaskPilot includes a Laravel Sail-based Docker stack for local development and product demos. The runtime is deliberately close to the app's real runtime dependencies so issues, agents, queues, and GitHub-aware flows can be exercised in a realistic local environment.

## Included services

The stack defined in `compose.yaml` includes:

- `laravel.test` — Laravel application container
- `caddy` — TLS terminator and HTTP entry point
- `mysql` — MySQL 8.4 database
- `redis` — cache and queue broker
- `meilisearch` — search provider
- `mailpit` — local SMTP + mail dashboard
- `queue` — background job worker
- `reverb` — Laravel Reverb websocket server

## Local startup

From the project root:

```bash
cp .env.example .env
composer install
npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The default Caddy entry point uses the `APP_URL` host from the environment, while the local app container remains available over the Sail-defined service network.

## Useful commands

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test
./vendor/bin/sail restart queue reverb
```

## Default local ports

- App / HTTPS: `${APP_PORT:-443}`
- Vite dev server: `${VITE_PORT:-5173}`
- MySQL: `${FORWARD_DB_PORT:-3306}`
- Redis: `${FORWARD_REDIS_PORT:-6379}`
- Meilisearch: `${FORWARD_MEILISEARCH_PORT:-7700}`
- Mailpit UI: `${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}`
- Reverb: `${FORWARD_REVERB_PORT:-8080}`

## Why this matters for the portfolio

This Docker setup is important because it mirrors the project's support services in a single developer-friendly stack. It gives a realistic, single-command environment for showing the workflow from issue intake through planning, implementation, review, and GitHub-aware status updates without requiring hand-tuned local configuration.

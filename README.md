# AI Smart & Self-Improving Digital Marketing Agency SaaS

A Laravel 13-based multi-tenant SaaS platform that automates digital marketing campaigns with AI-generated content, audience segmentation, real-time optimization, and continuous self-improvement.

## Features

- Multi-channel campaign creation (ads, email, social, SMS)
- AI-powered copy, image, audio, video generation (Laravel AI SDK)
- Audience segmentation & look‑alike modeling (vector search with pgvector)
- Real‑time bidding & budget optimization
- Performance analytics dashboard (JSON:API + WebSocket)
- Self‑improvement loop (nightly model retraining, versioned model registry)
- Multi-tenant SaaS with billing, role‑based access, MFA
- API & webhook ecosystem for 3rd‑party integrations

## Prerequisites

- Docker & Docker Compose
- PHP 8.3+ (if running locally)
- Composer
- Node.js & npm (for frontend assets)

## Quick Start (Docker)

```bash
# Clone the repository
git clone <repo-url>
cd ai-marketing-saas

# Copy environment file
cp .env.example .env

# Start containers
docker compose up -d --build

# Install PHP dependencies (if not done during build)
docker compose exec app composer install

# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate

# Start Horizon (optional)
docker compose exec app php artisan horizon

# Visit the application
http://localhost:8080
```

## Development

- Run tests: `php artisan test`
- Queue worker: `php artisan queue:work --redis`
- Horizon dashboard: `/horizon` (requires authentication)

## Architecture

See `docs/architecture.md` for detailed system design.

## License

Proprietary – Webbixray


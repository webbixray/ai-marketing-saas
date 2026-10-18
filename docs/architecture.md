# Architecture Overview

## High-Level Components

- **Frontend**: SPA (React/Vue) communicating via JSON:API
- **API Gateway**: Laravel Sanctum for SPA auth, Passport for machine‑to‑machine
- **Backend**: Laravel 13 (Octane + Horizon) handling requests, jobs, events
- **AI Services Layer**: Laravel AI SDK for text/image/audio/video generation, embeddings, vector search
- **Data Store**: PostgreSQL (primary), Redis (cache, queue, Horizon), pgvector for similarity search
- **Integrations**: Ad platforms (Meta, Google, TikTok), CRM (HubSpot, Salesforce), email services via webhooks
- **Observability**: Laravel Telescope, Prometheus + Grafana
- **Deployment**: Docker Compose (dev) / Kubernetes (prod) with Helm charts

## Key Modules

1. **Auth & Tenant** – multi‑tenant middleware, roles/permissions (Spatie)
2. **Billing** – Laravel Cashier (Stripe/Paddle)
3. **Campaign Manager** – CRUD, approval workflow
4. **Workflow Engine** – trigger‑based sequences (Queues, Events)
5. **AI Content Generator** – wrapper around Laravel AI SDK
6. **Vector Audience Service** – embedding storage & similarity search
7. **Analytics Engine** – event collection, metrics aggregation, real‑time dashboard
8. **Optimization Agent** – budget reallocation, bid adjustments
9. **Integration Hub** – adapters for ad platforms, CRM, email
10. **Admin Dashboard** – Nova/Livewire with charts
11. **DevOps** – Dockerfile, docker‑compose, CI/CD pipelines

## Data Flow

1. User creates campaign via UI → API request → Campaign model stored
2. Workflow triggers AI content generation → jobs dispatched to Horizon
3. Generated assets stored, campaign launched via platform APIs
4. Performance events streamed back via webhooks → stored in events table
5. Analytics service aggregates metrics → updates dashboard
6. Optimization agent evaluates performance → adjusts budgets/bids
7. Nightly job retrains models using fresh conversion data → versioned model registry

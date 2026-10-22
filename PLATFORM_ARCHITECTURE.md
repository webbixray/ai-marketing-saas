# Webbixray Digital Marketing Agency Platform - Architecture Document

## Overview

This is an enterprise-grade, multi-tenant SaaS platform for digital marketing agencies built on Laravel 13. It provides a complete suite of tools for managing clients, campaigns, tasks, billing, reporting, and AI-powered optimization - all with full multi-tenancy, role-based access control, and audit compliance.

---

## Core Architecture

### Multi-Tenancy
- **Global TenantScope** applied to all models
- Tenant identification via Sanctum token or session
- Complete data isolation between tenants
- Shared infrastructure, isolated data

### Authentication & Authorization
- **Laravel Sanctum** for API token authentication
- **Spatie Laravel Permission** for RBAC
- Roles: `admin` (full access), `user` (limited CRUD)
- API Key authentication for service-to-service calls
- Email verification & password reset flows

### Rate Limiting
| Endpoint Type | Limit | Scope |
|---------------|-------|-------|
| Global API | 100/min | Per user/IP |
| Auth endpoints | 10/min | Per IP |
| Health checks | 1000/min | Per IP |
| Billing | 30/min | Per user |
| Reports | 60/min | Per user |

---

## Domain Models

### Core Entities

| Model | Description | Key Features |
|-------|-------------|--------------|
| **Tenant** | Multi-tenant container | Users, clients, campaigns, settings |
| **User** | Platform users | Roles, permissions, API keys, notifications |
| **Client** | Agency clients | Health scoring, alerts, reports, goals, meetings |
| **Campaign** | Marketing campaigns | 50+ fields, SLA tracking, performance metrics |
| **Task** | Work items | SLA tracking, priority, assignment, pauses |
| **CreativeAsset** | Ad creatives | Performance scoring, fatigue detection, variants |

### Intelligence & Automation

| Model | Description |
|-------|-------------|
| **AutomationRule** | Event-driven rules with conditions & actions |
| **AutomationActionLog** | Audit trail for rule executions |
| **OptimizationInsight** | Historical optimization tracking |
| **ClientHealthScore** | Time-series health scoring |
| **ClientAlert** | Churn risk, upsell, performance alerts |
| **ClientReport** | Scheduled/on-demand reports |
| **ClientNPS** | Net Promoter Score tracking |
| **ClientGoal** | KPI tracking with progress |
| **SupportTicket** | Client support integration |

### Communication & Engagement

| Model | Description |
|-------|-------------|
| **ClientMeeting** | Meeting tracking with notes |
| **ClientCommunication** | Email/Slack/phone logging |
| **ClientPortalSession** | Client portal analytics |
| **ClientCommunication** | Response time tracking |

### Creative Management

| Model | Description |
|-------|-------------|
| **CreativeAsset** | Ad creatives with elements & performance |
| **CreativePerformanceDaily** | Daily performance time-series |

---

## Agent System Architecture

### Base Components

```
app/
├── Contracts/
│   ├── AgentContract.php          # Agent interface
│   └── AgentStatus.php            # Status enum
├── Domain/Agent/
│   ├── AgentContext.php           # Execution context with shared memory
│   ├── AgentResult.php            # Standardized result object
│   └── AgentCapability.php        # Capability enum (25+ capabilities)
├── Agents/
│   ├── BaseAgent.php              # Abstract base with hooks
│   ├── AgentOrchestrator.php      # Registration, execution, workflows
│   └── Intelligence/
│       ├── CampaignOptimizationAgent.php
│       ├── ClientIntelligenceAgent.php
│       └── CreativeIntelligenceAgent.php
```

### Agent Capabilities (25+)

| Category | Capabilities |
|----------|--------------|
| **Campaign** | create, optimize, analyze, budget_pace, creative_test, audience_expand |
| **Client** | health_score, churn_predict, upsell_identify, report_generate |
| **Creative** | performance_score, fatigue_detect, variant_generate, asset_manage |
| **Analytics** | attribution_calculate, incrementality_test, forecast_predict, anomaly_detect |
| **Automation** | rule_evaluate, workflow_execute, trigger_action, schedule_task |
| **Integration** | meta_api_sync, google_api_sync, linkedin_api_sync, tiktok_api_sync, ga4_api_sync |
| **Intelligence** | pattern_recognize, recommendation_generate, decision_make, learning_update |

### Agent Features

- **Shared Memory**: Context passed between agents in workflows
- **Hooks**: `beforeExecute`, `afterExecute`, `onInitialize`, `onShutdown`
- **Metrics Collection**: Automatic performance tracking
- **Health Checks**: Per-agent status monitoring
- **Parallel & Sequential Execution**: Pipeline and parallel workflows
- **Conditional Workflows**: If/then logic with field evaluation

---

## Intelligence Agents

### 1. CampaignOptimizationAgent
- **Purpose**: Continuous campaign optimization
- **Functions**:
  - Budget pacing analysis & adjustment
  - Bid optimization based on ROAS/CPA
  - Audience expansion for high performers
  - Creative fatigue detection triggers
- **Config**: Budget pacing, bid adjustments, max daily change %

### 2. ClientIntelligenceAgent
- **Purpose**: Proactive client management
- **Functions**:
  - Health scoring (5-factor weighted model)
  - Churn risk prediction (multi-factor)
  - Upsell opportunity identification
  - Automated report generation
- **Health Factors**: Engagement (25%), Performance (30%), Communication (20%), Budget (15%), Satisfaction (10%)

### 3. CreativeIntelligenceAgent
- **Purpose**: Creative lifecycle management
- **Functions**:
  - Performance scoring (CTR, CVR, ROAS, volume)
  - Fatigue detection (frequency, CTR decline, age)
  - Variant generation (headlines, media, CTAs, audiences)
  - Auto-archival of stale assets

---

## Automation Engine

### Rule Structure
```json
{
  "triggers": ["campaign.performance_low", "creative.fatigue_detected"],
  "conditions": [
    {"field": "campaign.roas", "operator": "lt", "value": 1.0}
  ],
  "condition_logic": "AND",
  "actions": [
    {"type": "pause_campaign", "params": {}},
    {"type": "send_notification", "params": {"user_id": 1, "message": "Campaign paused"}}
  ]
}
```

### Action Types (15+)
| Action | Description |
|--------|-------------|
| `pause_campaign` / `resume_campaign` | Campaign state control |
| `adjust_budget` | Percentage/fixed/set adjustments |
| `adjust_bid` | Bid modifications with limits |
| `send_notification` | In-app notifications |
| `create_task` | Task creation with assignment |
| `update_status` | Generic status updates |
| `send_email` | Email notifications |
| `call_webhook` | External webhook calls |
| `run_workflow` | Trigger nested workflows |
| `update_custom_field` | Dynamic field updates |

---

## Integration Hub

### Supported Platforms
| Platform | Status | Features |
|----------|--------|----------|
| **Meta (FB/IG)** | ✅ Full | Campaigns, metrics, creatives, audiences |
| **Google Ads** | 🟡 Partial | Campaigns, metrics |
| **LinkedIn** | 🟡 Partial | Campaigns, metrics |
| **TikTok** | 🟡 Partial | Campaigns, metrics |
| **GA4** | 🟡 Planned | Analytics, conversions |

### Features
- **Unified Campaign View**: Normalized cross-platform data
- **Aggregated Metrics**: Weighted averages across platforms
- **Auto-Sync**: Scheduled data synchronization
- **Credential Management**: Per-client & per-tenant tokens

---

## Recommendation Engine

### Categories & Types

| Category | Types | Trigger |
|----------|-------|---------|
| **Campaign** | pause, scale, creative_refresh | ROAS, CPA, CTR thresholds |
| **Budget** | increase, reallocation | Utilization %, ROAS |
| **Creative** | replace, testing, variant_gen | Fatigue, high performance |
| **Audience** | platform_expand, lookalike | Missing platforms, high ROAS |
| **Platform** | optimization | Platform-specific ROAS |
| **Automation** | rule_setup | Missing rules |

### Recommendation Structure
```json
{
  "type": "campaign_scale",
  "priority": "high",
  "title": "Scale High-Performing Campaign",
  "description": "Campaign X has ROAS 4.2 with 40% budget used",
  "action": "Increase budget 25%, expand lookalikes",
  "estimated_impact": "$12,450 additional revenue",
  "confidence": 0.8
}
```

---

## API & Documentation

### Endpoints (80+)
| Group | Endpoints |
|-------|-----------|
| **Auth** | register, login, logout, verify, password reset |
| **Health** | /health, /health/live, /health/ready |
| **Users** | profile, password, avatar, preferences |
| **Clients** | CRUD, campaigns, performance, health |
| **Campaigns** | CRUD, metrics, SLA, optimization |
| **Tasks** | CRUD, SLA tracking, assignment |
| **Billing** | Subscribe, swap, cancel, invoices |
| **Reports** | Revenue, growth, task/campaign stats |
| **Notifications** | CRUD, read/unread, preferences |
| **API Keys** | CRUD, scopes, regeneration |
| **Feature Flags** | CRUD, check, context-aware |

### Documentation
- **Scribe/OpenAPI 3.0**: Auto-generated from annotations
- **Postman Collection**: Ready-to-import
- **Interactive Try-It-Out**: Live testing in browser
- **Versioned**: `/api/v1/` prefix

---

## Observability & Operations

### Health Checks
- `GET /api/v1/health` - Basic liveness
- `GET /api/v1/health/live` - Kubernetes liveness probe
- `GET /api/v1/health/ready` - Readiness (DB, Cache, Redis)

### Laravel Pulse
- Real-time metrics dashboard
- Slow queries, requests, jobs, exceptions
- Cache, queue, server monitoring
- Protected by `auth` + `admin` middleware

### Structured Logging
- **Correlation IDs**: X-Correlation-ID header propagation
- **Request/Response Logging**: Full audit trail (sanitized)
- **Audit Channel**: Separate daily logs (90-day retention)
- **Correlation Context**: Automatic tenant/user enrichment

### Audit Logging
- Automatic on create/update/delete/restore
- Tracks: old/new values, user, IP, UA, URL
- Applied to: Campaign, Client, Task, User
- Test-safe (disabled during testing)

---

## Feature Flags

### System
- Context-aware evaluation (tenant, user roles, percentage rollout)
- DB-backed with caching (5-min TTL)
- API for runtime checks
- Admin management UI

### Example Flags
- `campaign_ai_assistant` - Enable AI campaign builder
- `new_billing_ui` - New billing dashboard
- `advanced_analytics` - Predictive analytics module

---

## Security

### Data Protection
- **Encrypted at rest**: Sensitive fields (tokens, PII)
- **Encrypted in transit**: TLS 1.3 enforced
- **Secret Management**: Environment variables, no hardcoded secrets

### Access Control
- **Multi-tenant isolation**: Global scope on all queries
- **RBAC**: Role + permission based
- **API Keys**: Scoped, IP-whitelisted, rotatable
- **Rate Limiting**: Multi-tier, configurable

### Compliance
- **Audit Trail**: Immutable logs for all changes
- **Data Retention**: Configurable per-tenant
- **GDPR Ready**: Export, deletion, consent tracking
- **SOC2 Aligned**: Access reviews, incident logging

---

## Deployment Architecture

### Requirements
- PHP 8.3+
- PostgreSQL 14+ (production) / SQLite (testing)
- Redis 7+ (cache, queues, Pulse)
- Node 18+ (Vite assets)

### Queue Workers
```bash
# Horizon for queue management
php artisan horizon

# Scheduled jobs
php artisan schedule:run
```

### Scheduled Jobs
| Job | Frequency | Purpose |
|-----|-----------|---------|
| Scheduled Reports | Monthly 1st, 06:00 | Generate & email client reports |
| Optimization Agents | Every 15 min | Campaign/client/creative optimization |
| Platform Sync | Hourly | Sync ad platform data |
| Health Checks | Every 5 min | Client health scoring |

---

## Testing Strategy

### Test Suite (16 tests, 58 assertions)
- **Unit Tests**: Models, services, agents
- **Feature Tests**: API endpoints, auth, billing, reporting
- **Database**: SQLite in-memory for speed
- **Isolation**: DatabaseMigrations trait per test

### CI/CD Ready
- GitHub Actions compatible
- Parallel test execution
- Coverage reporting

---

## Extensibility Points

### Adding New Agents
1. Extend `BaseAgent`
2. Implement required methods
3. Register in `AgentOrchestrator`
4. Add capabilities to `AgentCapability` enum

### Adding Automation Actions
1. Add case to `AutomationEngine::executeAction()`
2. Implement action method
3. Register in action type registry

### Adding Integrations
1. Extend `PlatformConnection`
3. Implement abstract methods
4. Register in `IntegrationHub`

### Custom Recommendations
1. Extend `RecommendationEngine`
2. Add new category method
3. Register in engine

---

## Current Metrics

| Metric | Value |
|--------|-------|
| **Models** | 25+ |
| **API Endpoints** | 80+ |
| **Agent Types** | 3 (extensible) |
| **Automation Actions** | 15+ |
| **Platform Integrations** | 4 |
| **Recommendation Types** | 18 |
| **Test Coverage** | 16 tests, 58 assertions |
| **Documentation** | OpenAPI 3.0 + Postman |

---

## Next Steps (Roadmap)

### Phase 1: Core Intelligence (Current)
- ✅ Agent framework
- ✅ 3 intelligence agents
- ✅ Automation engine
- ✅ Recommendation engine

### Phase 2: Advanced Analytics
- [ ] Attribution modeling (multi-touch)
- [ ] Incrementality testing framework
- [ ] Predictive forecasting (Prophet/ARIMA)
- [ ] Anomaly detection (Isolation Forest)

### Phase 3: AI-Powered Features
- [ ] Natural language campaign builder
- [ ] Auto-copy generation (GPT integration)
- [ ] Creative asset generation (DALL-E/Midjourney)
- [ ] Chat-based analytics query

### Phase 4: Enterprise Features
- [ ] SSO/SAML/OIDC
- [ ] SCIM provisioning
- [ ] White-label domains
- [ ] Multi-region deployment
- [ ] Advanced RBAC (resource-level)

### Phase 5: Marketplace
- [ ] Template library (campaigns, automations, reports)
- [ ] Custom agent marketplace
- [ ] Integration connectors store
- [ ] Professional services portal

---

## Getting Started

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Assets
npm run build

# Start services
php artisan serve
php artisan horizon
npm run dev
```

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=your-db-host
REDIS_HOST=your-redis-host

# Platform credentials
META_APP_ID=
META_APP_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
LINKEDIN_CLIENT_ID=
TIKTOK_APP_ID=

# Feature flags
PULSE_ENABLED=true
TELESCOPE_ENABLED=false
```

---

*Document Version: 1.0 | Last Updated: 2024 | Platform: Laravel 13 + PHP 8.3*

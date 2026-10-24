# Pilot UAT Plan — AI Marketing SaaS

**Purpose:** Pilot user-acceptance testing with 2-3 simulated pilot tenants before commercial launch.
**Standard:** All UAT-01..UAT-12 scenarios must pass for the pilot to sign off. Results recorded in UAT_RESULTS.md.

## Pilot Tenants (simulated)
- **Pilot A** — "Growth Studio" (agency): 3 users (owner+2 marketers), 2 clients, 3 campaigns
- **Pilot B** — "Acme Retail" (in-house): 2 users, 4 campaigns, active billing (plan: growth)

## Scenarios

### Onboarding & Access
- **UAT-01 Registration & login (Pilot A owner):** register via API with tenant, receive token, login, view profile. PASS criteria: token works, profile correct.
- **UAT-02 Admin setup:** platform admin creates plan, assigns Pilot B to plan; admin dashboard loads with stats.
- **UAT-03 Role enforcement:** marketer user cannot access /admin/* (403); admin can.

### Core Workflow (Pilot A)
- **UAT-04 Client management:** create client, add contact info, view client detail + performance.
- **UAT-05 Campaign lifecycle:** create draft campaign → activate → pause → complete. Status transitions validated.
- **UAT-06 Task management:** create tasks for campaign, assign to teammate, update status, complete.
- **UAT-07 Reporting:** run task-stats + revenue reports; numbers match seeded data.
- **UAT-08 Analytics:** campaign performance endpoint returns aggregated metrics for own tenant only.

### Security & Isolation (Pilot A vs Pilot B)
- **UAT-09 Tenant isolation:** Pilot B user attempts GET/PUT/DELETE on Pilot A resources → all blocked (404/403). No data in index lists.
- **UAT-10 API key lifecycle:** create API key (raw shown once), use it on an endpoint, revoke, confirm revoked key fails.
- **UAT-11 Rate limiting:** 11 rapid login attempts → 429 on 11th.

### Billing (Pilot B)
- **UAT-12 Subscription flow:** list plans, subscribe to growth plan, confirm subscription state, list invoices. (Stripe mocked in UAT env; real gateway in staging.)

## Environment
- Fresh sqlite (or staging MySQL), seeded pilot tenants
- Real HTTP server (`php artisan serve`), executed with curl/scripts — not in-process tests
- Results: PASS/FAIL per scenario + evidence (request/response summary)

## Sign-off
Pilot UAT passes when 12/12 scenarios PASS, no P0/P1 findings open, findings triaged and recorded.

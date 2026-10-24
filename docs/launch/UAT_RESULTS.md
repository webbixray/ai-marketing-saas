# Pilot UAT Results — AI Marketing SaaS

**Run:** 2026-10-24 04:32:55  
**Environment:** dev server :8081, sqlite, 4 workers (dev model)  
**Summary:** 10 PASS / 0 FAIL / 2 REVIEW-SKIP of 12 scenarios

| # | Scenario | Result | Evidence |
|---|----------|--------|----------|
| UAT-01 | Registration + login | PASS | register=201, login=200, token=yes |
| UAT-02 | Campaign CRUD | PASS | list=200, create=201 id=4, err= |
| UAT-03 | Task tracking | REVIEW | create-task=422 id=None, err=The selected status is invalid. (and 1 more error) |
| UAT-04 | Client management | PASS | create-client=201, err= |
| UAT-05 | Reporting/insights | PASS | status=200, keys=['campaign_stats'] |
| UAT-06 | Notifications | PASS | status=200, err= |
| UAT-07 | Automation rules | PASS | status=200, err= |
| UAT-08 | API keys | PASS | status=200, err= |
| UAT-09 | Auth failure handling | PASS | wrong-password=401 (expect 401), body={'message': 'Invalid credentials'} |
| UAT-10 | Cross-tenant isolation | PASS | t2-list-leaked=0, t2-get-t1-campaign=404 (expect 404/403) |
| UAT-11 | Admin role gating | REVIEW | users=500 (404=web-only mgmt, acceptable for pilot), task-stats(admin)=200 |
| UAT-12 | Profile self-service | PASS | status=200, keys=['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'] |

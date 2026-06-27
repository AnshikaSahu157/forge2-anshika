# Sprint 3 — React 19 Frontend

**Project:** PulseDesk — Multi-tenant Helpdesk SaaS (mini-Zendesk)
**Sprint Goal:** Working React 19 frontend with login, ticket list, ticket detail, dashboard, and API integration.
**Prerequisite:** Sprint 2 merged ✅ (REST API with auth, CRUD, comments, activity logs, tenant isolation)

---

## Issue List

### S3-01: React Frontend
**Assignee:** OpenClaw Bot
**Workspace:** `E:\forge2-anshika\frontend`

#### Tasks (in order)
1. **Project setup**
   - Vite + React 19 + Tailwind CSS
   - Clean folder structure (components, pages, hooks, api, utils)

2. **Login page**
   - Email + password form
   - Calls `POST /api/login`, stores Sanctum token (localStorage)
   - Redirects to dashboard on success

3. **Ticket list page**
   - Table/list of tickets
   - Filters: status, priority, assignee
   - Text search on subject/description
   - Pagination
   - Role-aware (customers see only their own tickets)

4. **Ticket detail page**
   - Full ticket info (subject, description, status, priority, assignee, tags)
   - Conversation thread: public comments + internal notes
   - Internal notes visible to admin/agent roles only
   - Reply form (public comment)
   - Internal note form (agents/admins)

5. **Dashboard**
   - Metrics: open tickets by status, by priority
   - Tickets created per day (simple bar/line chart)

6. **Navigation**
   - React Router (login → dashboard → tickets → ticket detail)
   - Navbar/sidebar with role-aware links
   - Protected routes (redirect to login if no token)

7. **API layer**
   - Axios instance with `Authorization: Bearer <token>` header
   - Base URL from env (`VITE_API_URL`)
   - 401 handler → redirect to login

8. **PR + Report**
   - Open PR to `main`
   - Post report in #s1-reports: What I Did / What's Left / What Needs Your Call

#### Acceptance Criteria
- [ ] Vite + React 19 + Tailwind project builds cleanly
- [ ] Login page authenticates via API and stores token
- [ ] Ticket list renders with working filters and search
- [ ] Ticket detail shows conversation thread with role-aware internal notes
- [ ] Dashboard displays metrics (status/priority breakdown, tickets/day)
- [ ] React Router handles navigation with protected routes
- [ ] Axios interceptor attaches auth token and handles 401
- [ ] PR opened to main
- [ ] Report posted in #s1-reports

---

### S3-02: QA Review (Bonus)
**Assignee:** OpenClaw Bot (QA reviewer role)
**Trigger:** After S3-01 report is posted

- Review the PR
- Manual smoke test of the frontend
- Post QA findings in #s1-qa

---

## Channels
- #sprint-main — Planning & assignments
- #s1-tasks — Task execution
- #s1-reports — Completion reports
- #s1-qa — QA findings

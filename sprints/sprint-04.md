# Sprint 4 — SLA, Activity Timeline, Notifications, CI

**Project:** PulseDesk — Multi-tenant Helpdesk SaaS (mini-Zendesk)
**Sprint Goal:** SLA breach tracking, activity timeline on ticket detail, in-app notifications, queue/assignment workflow, and final CI verification.
**Prerequisite:** Sprint 3 merged ✅ (React 19 frontend with login, ticket list, detail, dashboard)

---

## Issue List

### S4-01: SLA, Activity Timeline, Notifications & Queue
**Assignee:** OpenClaw Bot
**Workspace:** `E:\forge2-anshika\backend` + `E:\forge2-anshika\frontend`

#### Tasks (in order)

**Backend:**
1. **SLA breach logic**
   - Service/method to calculate SLA status per ticket using `sla_policies` (response_hours, resolution_hours) vs `ticket.created_at`
   - Return: time remaining, or `BREACHED` flag
   - Expose via ticket API response (or dedicated `/api/tickets/{id}/sla` endpoint)

2. **Activity log endpoint** (already exists from Sprint 2 — verify/enhance)
   - Ensure all ticket events are logged: status changes, assignments, replies, internal notes
   - `GET /api/tickets/{id}/activity` returns chronological timeline with user + timestamp + action

3. **Notifications**
   - Backend: store notifications (notification type, target user, ticket_id, read_at)
   - `GET /api/notifications` — list for current user
   - `POST /api/notifications/{id}/read` — mark as read
   - Create notification records on: ticket assignment, reply to a ticket you own/are assigned

4. **Queue/Assignment**
   - `PATCH /api/tickets/{id}/assign` — assign to agent
   - `PATCH /api/tickets/{id}/claim` — agent claims unassigned ticket
   - Backend validation: only agents/admins can claim/assign

5. **Seeder updates**
   - Link SLA policies to ticket priorities (e.g., urgent → 1h response / 4h resolution, high → 2h/8h, normal → 4h/24h, low → 8h/48h)
   - Ensure seed tickets have varied `created_at` timestamps (some breached, some within SLA)

6. **Pest tests**
   - SLA breach calculation logic (within SLA, breached, edge cases)
   - Notification creation on assignment/reply
   - Claim/assign endpoint access control

**Frontend:**
7. **SLA badge on ticket list + detail**
   - Show "Xh Ym remaining" or "BREACHED" badge (red) per ticket

8. **Activity timeline on ticket detail**
   - Chronological feed: status changes, assignments, replies — with avatar + name + timestamp
   - Styled as a vertical timeline

9. **Notifications dropdown**
   - Bell icon in navbar with unread count badge
   - Dropdown listing recent notifications
   - Mark-as-read on click

10. **Unassigned filter + claim button**
    - Ticket list: "Unassigned" quick filter
    - Ticket detail: "Claim" button for agents on unassigned tickets

**Finalize:**
11. **CI verification**
    - Ensure `php artisan test` passes (backend)
    - Ensure frontend builds cleanly (`npm run build`)
    - Verify no console errors on key flows

12. **PR + Report**
    - Open PR to `main`
    - Post report in #s1-reports: What I Did / What's Left / What Needs Your Call

#### Acceptance Criteria
- [ ] SLA breach calculation works — tickets show time remaining or BREACHED
- [ ] Activity timeline renders on ticket detail with full event history
- [ ] In-app notifications fire on assignment + reply
- [ ] Agents can claim unassigned tickets; admins can assign
- [ ] Seeders include SLA policies mapped to priorities with varied timestamps
- [ ] Pest tests pass for SLA logic, notifications, claim/assign
- [ ] Frontend builds with no errors
- [ ] SLA badges, activity timeline, notifications dropdown, unassigned filter all functional
- [ ] PR opened to main
- [ ] Report posted in #s1-reports

---

### S4-02: QA Review (Bonus)
**Assignee:** OpenClaw Bot (QA reviewer role)
**Trigger:** After S4-01 report is posted

- Review the PR
- Run Pest tests + frontend build independently
- Post QA findings in #s1-qa

---

## Channels
- #sprint-main — Planning & assignments
- #s1-tasks — Task execution
- #s1-reports — Completion reports
- #s1-qa — QA findings

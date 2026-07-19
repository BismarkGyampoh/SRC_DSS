# SRC Project Selection DSS — System Documentation

> University of Mines and Technology (UMaT) — Students' Representative Council (SRC)
> Multi-Criteria Decision Support System for SRC Project Selection
> Version 2.0 (UMaT-Specific)

---

## 1. Overview

The **SRC Project Selection DSS** is a web-based decision support system that helps the UMaT SRC Executive Board select student projects to fund within a semester's budget and volunteer-hour constraints. It combines a **Knapsack optimization engine**, a **multi-criteria scoring model (PIS)**, a **rule-based expert system**, a **Group Decision Support System (GDSS)**, and a **what-if sandbox simulator**, wrapped in a role-based portal with admin, knowledge, data, model, and UI management layers.

The system is built in **vanilla PHP (no framework)** with **MySQL (InnoDB)**, uses **Materialize CSS** + a custom `app.css` design system, and is deployed on **InfinityFree** (`sql301.infinityfree.com`, database `if0_41105880_src_dss_db`).

---

## 2. How the System Works

This section explains the end-to-end behaviour: who does what, and how a submitted project becomes a funded decision.

### 2.1 The actors and their flow

```
             Financial Secretary              Projects Coordinator            Executive Board
                  (sets limits)                   (submits ideas)                (makes the pick)
                       │                                │                              │
        ┌──────────────┴──────────┐      ┌──────────────┴────────────┐     ┌──────────┴──────────┐
        │ constraints.php         │      │ proposal.php              │     │ optimization.php    │
        │  • budget ceiling        │      │  • submit project draft   │     │  • run Project Pick │
        │  • volunteer-hour ceiling│      │  • 8 criteria scores      │     │  • Smart Risk Check │
        │  • reach / speed weights │      │  • attachments, comments  │     │  • manual override  │
        └──────────────┬──────────┘      └──────────────┬────────────┘     └──────────┬──────────┘
                       │                                │                              │
                       ▼                                ▼                              ▼
                 semester_constraints          projects (status: Draft→Pending)   Knapsack engine
                 criteria_weights                                       (reads Pending, writes Accepted/
                                                                          Rejected/Deferred)
                                                                                  │
                                                                                  ▼
                                                                          audit_logs (report)
                                                                                  │
                                                                                  ▼
                                                            public_dashboard.php  (Accepted only, live)
```

The **Faculty Representative** and **Student Representative** only consume the public dashboard and submit feedback; **Admin** oversees config, users, data, knowledge, models, and UI.

### 2.2 Lifecycle of a single project

1. **Draft** — Projects Coordinator saves an incomplete proposal (`action_add_project.php` with `action=draft`). Not yet visible to the Board.
2. **Pending** — Coordinator submits it. Criteria scores (0–100 on 8 dimensions) and budget/hours/reach/weeks are stored; `AutomationService` runs data-quality checks (e.g. budget > 50,000 GHS, hours > 200 → `data_quality_checks`).
3. **Scoring (PIS)** — before selection, `calculatePIS()` recomputes each Pending project's **Project Impact Score**.
4. **Selection** — Executive Board runs the Project Picker (`runKnapsack()`). Each project is assigned **Accepted**, **Rejected**, or **Deferred**.
5. **Override** — Board may manually change any status with a mandatory reason (`project_overrides`).
6. **Delivery & tracking** — accepted projects get milestones, actual budget/hours, feedback, and comments; `budget_variance` is tracked.
7. **Transparency** — only `Accepted` projects surface on the public dashboard and the JSON API.

### 2.3 PIS — how a project is scored

`calculatePIS()` (`KnapsackOptimizationService`) builds a 0–1 weighted score for every Pending project:

```
PIS = reach_weight × reachScore
    + speed_weight × speedScore
    + Σ (criteria_weightᵢ × criteriaScoreᵢ)        // 8 criteria
```

- **reachScore** = `student_reach / max(student_reach)` across all Pending projects (normalised 0–1).
- **speedScore** = faster projects score higher: `(maxWeeks − weeks + 1) / (weekRange + 1)`.
- **criteriaScoreᵢ** = each of the 8 scores (0–100) ÷ 100.
- **Weights** come from two places:
  - `semester_constraints.reach_weight` + `speed_weight` (set by Financial Secretary / GDSS),
  - `criteria_weights` — eight equal-ish weights (~0.10–0.15) summing to 1.0 (defaults used if table missing).

Result is rounded to 4 decimals and written to `projects.calculated_pis`.

### 2.4 The Knapsack selection (the core decision)

`runKnapsack()` solves a **2-constraint 0/1 knapsack**:

- **Capacity** = semester `max_available_budget` (GHS) **and** `max_volunteer_hours`.
- **Items** = all Pending projects, each with:
  - `weight` = budget_required,
  - `hours` = volunteer_hours,
  - `value` = calculated_pis.
- **Algorithm** = dynamic programming over a `(budget × hours)` grid (`SplFixedArray` + a bit-packed `keep` matrix) that maximises total PIS without exceeding either capacity.
- Projects are pre-sorted by `calculated_pis DESC, student_reach DESC, budget ASC` so ties favour higher impact and lower cost.

**After solving**, every unselected project is split:
- **Deferred** if its PIS ≥ 0.5000 (good but didn't fit this term),
- **Rejected** if PIS < 0.5000.

The run is logged to `audit_logs` (full HTML report) and `model_executions`, and an activity event is recorded.

### 2.5 Guardrails around the decision

- **Smart Risk Checker** (`ProjectExpertSystem`) evaluates the active `expert_rules` (JSON conditions in `knowledge_categories`) against Pending projects *before* picking, flagging budget risk, volunteer burnout, ROI, logistics, etc. (severity Critical → Advisory).
- **GDSS** lets stakeholders vote their preferred `reach_weight`/`speed_weight`; *finalize* aggregates those votes into `semester_constraints`, shifting what the optimizer favours.
- **Sandbox** lets the Board simulate "what if budget/hours/weights change" without touching live data.
- **Override history** and **audit trail** keep every decision explainable and reversible-with-reason.

### 2.6 Why you might not see projects

- Projects seed as **Pending**; the Optimization board only lists **Accepted/Rejected/Deferred** — run the Picker first.
- Pending projects appear under **Proposal → My Projects / All Projects** (Coordinator) and via the API `action=projects` (Accepted only).
- If *nothing* shows anywhere, the DB import failed (see §12 — use `setup_schema_infinityfree.sql`).

---

## 3. Architecture

```
DSS/
├── index.php                      # Landing page → redirects to public dashboard
├── login.php                      # Executive login screen
├── logout.php                     # Logout
├── config/
│   ├── auth.php                   # Sessions, CSRF, role/permission guards, maintenance mode
│   └── database.php               # PDO connection to InfinityFree MySQL
├── lang/
│   ├── index.php                  # Translation engine (__('key'))
│   ├── en.php                     # English strings
│   └── gh.php                     # Twi (GH) strings
├── includes/
│   ├── header.php                 # App shell, nav, notifications, user menu, theme
│   ├── footer.php                 # Scripts / closing markup
│   └── public_header.php          # Header for public (unauthenticated) pages
├── api/
│   └── index.php                  # JSON API (key-protected)
├── views/                         # All UI pages
│   ├── data_management/           # admin_data_management.php
│   ├── knowledge_management/      # admin_knowledge_management.php
│   ├── model_management/          # admin_model_management.php
│   ├── user_interface/            # admin_dashboard_manager.php
│   └── ... (page files)
├── controllers/                   # POST action handlers (one per form/action)
│   ├── data_management/
│   ├── knowledge_management/
│   ├── model_management/
│   ├── user_interface/
│   └── action_*.php
├── services/                      # Business logic (reusable classes)
│   ├── data_management/DataManagementService.php
│   ├── knowledge_management/ProjectExpertSystem.php
│   ├── model_management/ (KnapsackOptimizationService, AuditTrailService,
│   │                    AutomationService, ModelManagementService)
│   ├── user_interface/DashboardService.php
│   ├── EmailService.php
│   ├── TwoFactorAuth.php
│   ├── ActivityLogger.php
│   └── NotificationService.php
├── public/
│   ├── css/app.css                # Design system / theme (light + dark)
│   ├── images/                    # logos, assets
│   └── uploads/avatars/           # user profile pictures
├── database/
│   ├── setup_schema.sql           # Original schema (needs editing for InfinityFree)
│   ├── setup_schema_infinityfree.sql  # CLEAN import file (FK-safe, no DB-create)
│   ├── umtat_enhancement_migration.sql
│   └── migrate_project_votes.php
└── docs/                          # test_engine.php, this doc, etc.
```

**Request flow:** A `view/*.php` page `require`s `includes/header.php` → which loads `config/auth.php` + `config/database.php` + `lang/index.php`, enforces `requireRole(...)`, renders HTML, and on submit posts to a `controllers/action_*.php` handler that validates CSRF, mutates the DB via `services/*`, logs activity, and redirects back.

---

## 4. Database (`if0_41105880_src_dss_db`)

Engine: InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.

### Core user & config tables
| Table | Purpose |
|---|---|
| `src_users` | Accounts: username, `password_hash` (bcrypt), `user_role`, profile, `theme_preference`, 2FA fields, `email_notifications`, `language`. |
| `system_config` | Single-row settings: `active_academic_year`, `maintenance_mode`. |
| `semester_constraints` | Per-term `max_available_budget`, `max_volunteer_hours`, `reach_weight`, `speed_weight`, `set_by_user_id`. |
| `criteria_weights` | Per-term multi-criteria weights (8 criteria), `UNIQUE(term)`. |
| `notifications` | Per-user in-app notifications (`is_read`, `type`). |
| `activity_logs` | Audit trail of all user actions (who/what/when/old+new values). |
| `audit_logs` | Stored HTML optimization reports (`report_html`). |
| `user_permissions` | Per-user per-page `can_view/edit/delete` overrides. |
| `user_dashboard_config` | JSON dashboard config per user. |

### Project tables
| Table | Purpose |
|---|---|
| `projects` | Core entity. Budget, hours, reach, weeks, `calculated_pis`, `dss_status` (Draft/Pending/Accepted/Rejected/Deferred), actuals, 8 criteria score columns, receipt/petition paths. |
| `project_criteria_scores` | Normalized criteria scores (1 row per criteria per project). |
| `project_overrides` | Recorded manual status overrides with reason. |
| `project_templates` | Reusable proposal templates (`criteria_scores_json`). |
| `project_milestones` | Project delivery milestones. |
| `project_feedback` | Student feedback + `delivery_status`. |
| `project_attachments` / `feedback_attachments` | File uploads. |
| `project_dependencies` | Prerequisite/conflict links between projects. |
| `project_comments` | Threaded comments (self-referential). |
| `project_votes` | User ratings per project. |

### GDSS & data/knowledge/model layers
| Table | Purpose |
|---|---|
| `gdss_votes` | GDSS `reach_weight`/`speed_weight` votes per user/term (`UNIQUE(user,term)`). |
| `data_sources` | Registered import sources. |
| `data_imports` | CSV/API import batch records. |
| `data_quality_checks` | Violations found (Info→Critical). |
| `knowledge_categories` | Expert-rule categories. |
| `expert_rules` | JSON-condition rules + recommendations. |
| `rule_trigger_log` | When rules fired. |
| `dss_models` / `model_parameters` / `model_executions` | Model registry + runs. |
| `user_dashboards` / `dashboard_widgets` | Customizable dashboards. |

### Seed data
- 6 roles seeded (fin_sec, proj_coord, exec_board, admin, faculty_rep, student_rep) — all password `password123` (bcrypt). **Change before real deployment.**
- `2025/2026 Semester 1` constraints: budget 50,000 GHS, 500 hours, reach 0.6 / speed 0.4.
- 8 criteria weights summed to 1.0.
- 7 project templates; 5 seed projects (all `Pending`).
- Models: Knapsack Optimizer, PIS Scorer, Expert System, GDSS Aggregator, Sandbox Simulator.

---

## 5. Authentication & Security

- **Sessions:** `config/auth.php` starts secure HTTP-only, `Lax` SameSite cookie; session regenerated on login/logout.
- **Passwords:** bcrypt (`password_hash`/password_verify).
- **CSRF:** every form carries `$csrfField`; `requireCsrfToken()` validates via `hash_equals`.
- **Roles:** 6 roles — Financial Secretary, Projects Coordinator, Executive Board, Admin, Faculty Representative, Student Representative.
- **Guards:**
  - `requireRole([...])` — hard gate per page.
  - `requirePermission($page, $action)` — fine-grained override (Admins bypass).
  - `isMaintenanceMode()` / `enforceMaintenanceMode()` — only Admins allowed during maintenance.
- **2FA:** `TwoFactorAuth.php` + `setup`/`verify` pages; login_action reroutes to 2FA step if enabled.
- **API auth:** `api/index.php` requires `X-API-KEY` / `api_key` = `src-api-key-2026` (hardcoded — move to config/env for production).

---

## 6. Roles & Permissions (navigation map)

| Role | Landing page | Key access |
|---|---|---|
| **Projects Coordinator** | proposal.php | Submit/Manage projects, My Drafts, Carry-Forward, Templates, Milestones |
| **Executive Board** | optimization.php | Project Selection (optimizer), Try-Out/Sandbox, GDSS vote/finalize, Budget Reports |
| **Financial Secretary** | constraints.php | Term Budget/constraints, Budget Reports |
| **Admin** | admin_dashboard.php | Everything: System Dashboard, Settings, Activity Logs, Data/Knowledge/Model/UI Mgmt |
| **Faculty Representative** | public_dashboard.php | Public Dashboard, Feedback |
| **Student Representative** | public_dashboard.php | Public Dashboard, Feedback |

Navigation is role-conditional in `includes/header.php` (top links + "More" dropdown).

---

## 7. Core Feature Modules

### 6.1 Project Lifecycle (Projects Coordinator)
- **Submit:** `contollers/action_add_project.php` + `views/proposal.php`. Validates title (5–255), budget (≤1,000,000 GHS), hours (≤10,000), reach (≤100,000), weeks (≤520). Saves as `Draft` or `Pending`. Stores 8 criteria scores + writes to `project_criteria_scores`. Triggers data-quality checks (budget >50k, hours >200) and `AutomationService::onProjectCreated`.
- **Drafts:** `views/drafts.php`, submit via `action_submit_draft.php`.
- **Templates:** `views/templates.php` prefill proposals.
- **Carry-Forward (Rollover):** `views/rollover.php` carries projects into a new term.
- **Tracking:** `action_update_project_tracking.php` records actual budget/hours/variance.

### 6.2 Constraints & Budget (Financial Secretary)
- **Term Budget:** `views/constraints.php` + `action_set_constraints.php`. Sets semester budget, volunteer hours, reach/speed weights (`semester_constraints`).
- **Budget Analytics:** `views/budget_analytics.php` — accepted budget totals, charts per term.

### 6.3 Optimization Engine (Executive Board)
- **Project Selection:** `views/optimization.php`. Shows Accepted/Rejected/Deferred (Pending excluded here). Filters by keyword/status/term/budget. Run picker → `model_management/action_run_engine.php`.
- **Knapsack engine:** `KnapsackOptimizationService::runKnapsack()`. Picks max-value project set within budget + hours limits using `PIS` score. Logs to `audit_logs` + `model_executions`.
- **PIS scoring:** `calculatePIS()` normalizes student reach, implementation speed, cost-efficiency (reach/budget), and 8 criteria weights → `calculated_pis`.
- **Override tool:** `action_override_project.php` / `action_bulk_override.php` change statuses with reason → `project_overrides`.
- **Compare System vs Manual:** `AuditTrailService::generateComparativeReport()`.

### 6.4 Expert System (Risk)
- `views/optimization.php` "Smart Risk Checker" → `action_run_expert_system.php` → `ProjectExpertSystem` evaluates active `expert_rules` (`condition_json`) against pending projects, returns severity-ranked recommendations, logs to `rule_trigger_log`.

### 6.5 GDSS (Group Decision)
- `action_gdss_vote.php` + `action_gdss_finalize.php`. Stakeholders vote `reach_weight`/`speed_weight` per term (`gdss_votes`, unique per user). Finalize aggregates weights into `semester_constraints`.

### 6.6 Sandbox Simulator
- `views/sandbox.php` + `action_run_sandbox.php` + `action_run_goalseeking.php`. What-if scenarios adjusting budget/hours/weights without persisting.

### 6.7 Public Transparency Dashboard
- `index.php` → `views/public_dashboard.php` (no auth). Lists Accepted projects + term selector + budget utilization. Also exposed via `api/index.php` (`action=projects`, `action=status`).

---

## 8. Management Layers (Admin)

| Module | View | Service / Controllers |
|---|---|---|
| **Data Management** | `data_management/admin_data_management.php` | `DataManagementService`, `action_bulk_import.php`, `action_admin_db_utils.php` (truncate/clear), `action_admin_backup.php`, `action_admin_export.php` |
| **Knowledge Management** | `knowledge_management/admin_knowledge_management.php` | `ProjectExpertSystem`, `action_manage_rules.php` |
| **Model Management** | `model_management/admin_model_management.php` | `ModelManagementService`, `action_manage_models.php` |
| **UI Manager** | `user_interface/admin_dashboard_manager.php` | `DashboardService`, `user_interface/action_save_dashboard.php`, `action_load_dashboard.php` |
| **System Settings** | `admin_config.php` | `action_admin_config.php` (active year + maintenance), `action_admin_optimize.php` (OPTIMIZE TABLE), `action_admin_manage_users.php`, `action_admin_permissions.php`, `action_admin_audit_logs.php` |
| **Activity Logs** | `activity_logs.php` | `activity_logs` table viewer |

---

## 9. Services (Business Logic)

| Service | Responsibility |
|---|---|
| `KnapsackOptimizationService` | PIS calc + knapsack selection. |
| `AuditTrailService` | Generates HTML optimization/override/comparative reports from `audit_logs`. |
| `AutomationService` | Hooks on project create/update (DQ checks, notifications). |
| `ModelManagementService` | Register/complete `model_executions`, manage `dss_models`/`model_parameters`. |
| `ProjectExpertSystem` | Loads `expert_rules`, evaluates JSON conditions, formats advice. |
| `DataManagementService` | Import/export/quality-check orchestration. |
| `DashboardService` | Custom dashboard layout/state. |
| `NotificationService` | In-app notifications. |
| `ActivityLogger` | Writes `activity_logs` (used app-wide). |
| `TwoFactorAuth` | TOTP-style 2FA setup/verify. |
| `EmailService` | Reads SMTP from `system_config` (keys optional; degrades gracefully). |

---

## 10. User Profile & Preferences

- **Profile:** `views/profile.php` + `action_update_profile.php` (display name, bio, picture, email, phone), `action_change_password.php` (bcrypt rehash), `action_update_theme.php` (light/dark persisted), `action_change_language.php` (en/gh), `action_two_factor.php` (setup/disable), `action_update_notification_settings.php`.
- **Theme:** light/dark via `app.css` `[data-theme]`, persisted in `src_users.theme_preference` + `localStorage`.
- **Language:** EN / Twi via `lang/`.

---

## 11. API

`api/index.php` (JSON, key-protected):
- `?action=projects` → Accepted projects ordered by PIS.
- `?action=status` → project counts grouped by `dss_status`.
- Returns `401` on bad/missing key; `400` on unknown action.

---

## 12. Deployment Notes (InfinityFree)

- DB host `sql301.infinityfree.com`, db `if0_41105880_src_dss_db`.
- **Use `database/setup_schema_infinityfree.sql`** for imports (not the raw `setup_schema.sql`):
  - Wraps script in `SET FOREIGN_KEY_CHECKS = 0;` … `= 1;` (InfinityFree enforces FKs on DROP).
  - Removes `CREATE DATABASE`/`USE src_dss_db` (not permitted / would target wrong DB).
  - Removes the broken trailing `INSERT INTO system_config (config_key, config_value …)` (columns don't exist — single-row schema).
- Seed users share password `password123`; change before production.

---

## 13. Known Issues / Tech Debt

1. **`system_config` schema conflict** — `EmailService.php` expects key/value columns (`config_key`,`config_value`) that don't exist; it degrades gracefully but SMTP never works. Either add the columns or refactor `EmailService`.
2. **Hardcoded API key** `src-api-key-2026` in `api/index.php`.
3. **Hardcoded term** `'2025/2026 Semester 1'` in multiple controllers (add project, run engine) instead of reading `system_config.active_academic_year`.
4. **Duplicate activity logging** in `action_add_project.php` (logs CREATE twice).
5. **Seed insert for projects uses `submitted_by = 2`** (proj_coord) — fine, but all seed projects are `Pending` so they never appear on the Optimization board until a picker run or manual Accept.
6. **`ALTER TABLE` migrations at runtime** (login_action adds 2FA cols; optimization.php adds actual_budget/variance/actual_volunteer_hours) — schema drift risk; prefer a single migration file.
7. **No automated tests** beyond `docs/test_engine.php`.
8. **No rate limiting** on login; CSRF present but no brute-force protection.

---

## 14. Quick Start

1. Import `database/setup_schema_infinityfree.sql` into `if0_41105880_src_dss_db`.
2. Confirm `config/database.php` credentials (host/user/pass/db).
3. Login at `/login.php` (e.g. `admin` / `password123`).
4. Financial Secretary sets Term Budget → Projects Coordinator submits projects → Executive Board runs Project Picker → review & override → public dashboard reflects Accepted projects.

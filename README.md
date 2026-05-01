# Client Listing

Client Listing is a multi-tenant project and issue management app built around a simple workflow:

**Clients -> Projects -> Issues -> Sub-Issues**

The goal is to give each user a private workspace where they can manage their own clients, projects, issue tracking, screenshots, and nested work items. A Super Admin manages user accounts, while normal users only see and work with their own data.

## What It Does

- **Multi-tenant workspaces:** every client, project, and issue belongs to a specific user.
- **Super Admin user management:** admins can create, update, and manage users from an admin page.
- **Client management:** compact table-style client list with create/edit/delete flows.
- **Project management:** projects belong to clients and include a project detail page with paginated top-level issues and issue actions.
- **Issue management:** issues support status updates, project reassignment, parent linking, rich descriptions, and scoped issue context.
- **Recursive sub-issues:** users can create child issues directly from the issue page, including deeper nested hierarchies.
- **Kanban board:** visual Todo / In Progress / Done board for scanning and moving work quickly.
- **Issue attachments:** multiple images and files can be uploaded per issue.
- **Issue references:** internal and external links can be added to issues for supporting context.
- **Rich text descriptions:** description fields use a lightweight rich text editor instead of plain textareas.
- **Compact professional lists:** Clients, Projects, Issues, Project Detail issues, and Admin Users use clean table-style panels.
- **Pagination:** list-style pages include pagination, while Kanban intentionally stays unpaginated for a smoother board experience.
- **Polished status badges:** Todo, In Progress, and Done use distinct colors so status changes are easy to scan.
- **Engaging UI:** sidebar navigation, responsive layout, reusable modal, SweetAlert2 dialogs, loading states, and inline validation feedback.

## Advanced Enhancements

- **Issue tagging system:** project-scoped tags can be attached to issues and surfaced in list/detail views.
- **Issues list search upgrades:** filter by project, status, tag, and free-text query across issue title/description.
- **Done date semantics:** issues track completion timing with `done_at`, and done workflows display both created and completed dates where relevant.
- **Attachment lifecycle controls:** images, files, and links support inline delete actions from the issue detail experience.
- **Image gallery modal:** issue screenshots open in a dedicated modal with next/previous navigation for easier review.
- **Upload policy improvements:** attachment uploads support multi-select and enforce practical size limits, with predictable datetime-prefixed filenames.
- **Controller/service separation:** issue orchestration logic has been refactored into `IssueService` to keep controller actions slimmer and reusable.
- **Dashboard engagement layer:** dashboard includes status tabs plus weekly/monthly created-vs-completed analytics visuals.
- **Kanban engagement layer:** Kanban includes richer progress cues, target tracking, and clearer completion flow indicators.
- **Daily Activity page:** dedicated date-focused drilldown for created/completed work with calendar heat-style highlights and lane-level inspection.
- **Configurable execution targets:** daily target and stale/critical thresholds are configurable through admin settings (with environment fallbacks).
- **Gentle nudge system:** cross-page stale-work cues (topbar, dashboard, issues list, kanban lanes, daily carryover) with escalating visual severity.

## Tech Stack

Backend:

- Laravel 13
- PHP 8.3+
- MySQL
- Custom session-based authentication
- Laravel Storage for issue images
- Laravel Form Requests for validation

Frontend:

- Vue 3
- Inertia.js
- Vite
- Bootstrap 5
- SweetAlert2
- Lucide Vue icons

## Main Pages

- `/login` - custom login page
- `/dashboard` - workspace summary and recent issue activity
- `/clients` - client list and management
- `/projects` - project list and management
- `/projects/{project}` - project detail with top-level issues
- `/issues` - issue list and filters
- `/issues/{issue}` - issue detail, images, context, and nested issue tree
- `/kanban` - issue board grouped by status
- `/admin/users` - admin-only user management

## Installation

1. Install PHP dependencies:

```bash
composer install
```

2. Install frontend dependencies:

```bash
npm install
```

3. Create the environment file:

```bash
cp .env.example .env
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
```

4. Generate the Laravel app key:

```bash
php artisan key:generate
```

5. Configure MySQL in `.env`.

The default example uses:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=client_issues_listing
DB_USERNAME=root
DB_PASSWORD=
```

Create the database in MySQL before running migrations.

6. Run migrations:

```bash
php artisan migrate
```

7. Link public storage for issue images:

```bash
php artisan storage:link
```

8. Build frontend assets:

```bash
npm run build
```

For local development, run Vite instead:

```bash
npm run dev
```

9. Start the Laravel server if you are not using a local virtual host:

```bash
php artisan serve
```

## Demo Accounts

If you have already created the local demo users, you can log in with:

- Admin: `admin@example.com` / `password123`
- Normal user: `user@example.com` / `password123`

If those accounts do not exist in your database yet, create them through your preferred local seeding or Tinker workflow.

## Notes

- Kanban intentionally does not use pagination because paginated boards feel awkward for workflow scanning.
- Issue descriptions are stored as formatted HTML from the rich text editor.
- Uploaded issue images are stored on Laravel's public disk under the `issues` folder.
- Multi-tenancy is enforced in the model layer so users only access their own client, project, and issue data.

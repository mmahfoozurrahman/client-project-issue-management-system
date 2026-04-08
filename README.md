# Client Listing

Client Listing is a multi-tenant project and issue management app built around a simple workflow:

**Clients -> Projects -> Issues -> Sub-Issues**

The goal is to give each user a private workspace where they can manage their own clients, projects, issue tracking, screenshots, and nested work items. A Super Admin manages user accounts, while normal users only see and work with their own data.

## What It Does

- **Multi-tenant workspaces:** every client, project, and issue belongs to a specific user.
- **Super Admin user management:** admins can create, update, and manage users from an admin page.
- **Client management:** compact table-style client list with create/edit/delete flows.
- **Project management:** projects belong to clients and include a polished project detail page with paginated top-level issues.
- **Issue management:** issues include status, rich descriptions, project/client context, screenshots, and nested sub-issues.
- **Recursive sub-issues:** users can create child issues directly from an issue detail page, including deeper nested children.
- **Kanban board:** visual Todo / In Progress / Done board for issue flow.
- **Issue image uploads:** multiple JPG/PNG images can be attached to issues.
- **Rich text descriptions:** description fields use a lightweight rich text editor instead of plain textareas.
- **Compact professional lists:** Clients, Projects, Issues, Dashboard activity, Project Detail issues, and Admin Users use clean table-style panels.
- **Pagination:** list-style pages include pagination, while Kanban intentionally stays unpaginated for a smoother board experience.
- **Polished status badges:** Todo, In Progress, and Done use distinct colors so status changes are easy to scan.
- **Engaging UI:** sidebar navigation, responsive layout, reusable modal, SweetAlert2 toasts, loading states, validation feedback, and a warm card-based visual style.

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

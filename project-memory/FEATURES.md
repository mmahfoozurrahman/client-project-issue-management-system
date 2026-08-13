# Project Feature Memory

This is a newest-first record of features and meaningful product changes, consolidated from the repository Git history. It is intended as working project memory rather than a verbatim commit log.

## Current snapshot

- **Latest commit:** `087ff83` - global date formatting utility extraction and human readable tag dates.
- **Workflow:** Clients -> Projects -> Issues -> Sub-Issues.
- **Stack:** Laravel 13, PHP 8.3+, MySQL, Vue 3, Inertia.js, Vite, Bootstrap 5, SweetAlert2, TipTap, and Chart.js.

## Feature history (newest first)

### Tag Management autocomplete suggestions

**Last updated:** 13th August, 2026

- Added existing-tag autocomplete suggestions to the Tag Management search field, scoped to the selected project when one is chosen.

### Rich-text table support & copy-paste structure retention

**Last updated:** 11th August, 2026

- Added TipTap table extensions (`@tiptap/extension-table`) to `RichTextEditor.vue`, supporting full table insertion and editing controls (`⊞ Table`, `+Row`, `-Row`, `+Col`, `-Col`, `✕ Table`).
- Updated `RichTextSanitizer.php` HTML whitelist to permit table elements (`table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`, `caption`, `colgroup`, `col`) while stripping unsafe tags.
- Added responsive table CSS styles for `.ProseMirror` editor canvas and `.rich-display` preview elements.
- Enabled seamless copy-pasting of formatted table data from external sources (Excel, Word, web pages) into issue descriptions.

### Tag issue count link & premium filter search input layout

**Last updated:** 7th August, 2026

- Linked the issue count pill (`{count} issue(s)`) in Tag Management (`/tags`) to the issues list page (`/issues?tag_id={id}&project_id={project_id}`).
- Refactored `.filters-row` from fixed 220px max grid columns to a flexible flex layout with auto-expanding search inputs and rounded pill controls for a premium, spacious search experience across Tag, Issue, and Project listing pages.

### Tag management listing order & human-readable date formatting

**Last updated:** 7th August, 2026

- Updated Tag Management (`/tags`) list query to order tags by most recently created (`created_at` DESC, `id` DESC) instead of alphabetically.
- Extracted date formatting into global reusable helper functions in `resources/js/utils/date.js` (`formatDate`, `formatIssueDate`) and refactored Vue pages to use it.

### Tag input autocomplete suggestions

**Last updated:** 4th August, 2026

- Added autocomplete suggestions to tag input fields on the issue show page for smoother tag selection.

### Project issue-list date column

**Last updated:** 4th August, 2026

- Added a date column to the project issue list.
- Follow-up refinements improved the presentation and behaviour of this column.

### Frosted-glass sidebar refresh

**Last updated:** 4th August, 2026

- Refreshed the sidebar with a frosted-glass visual treatment.
- Removed visible scrollbar treatment and corrected responsive overlap behaviour.

### Tag management and access control

**Last updated:** 4th August, 2026

- Added a Tag Management sidebar link.
- Added project-scoped tag administration for super admins, owners, and developers.
- Added pagination and filters to tag management.
- Enforced duplicate-tag prevention within a project.

### Issue detail and project overview improvements

**Last updated:** 3rd August, 2026

- Added a quick-read modal for issues from the project show page.
- Added a pinned-issues list card to the project page.
- Corrected related-issue sorting on the issue detail page.
- Adjusted the project show-page experience through several UI refinements.

### Rich-text editor enhancements

**Last updated:** 23rd July, 2026

- Upgraded the shared rich-text editor to TipTap formatting.
- Added text highlighting.
- Sanitized saved rich-text descriptions and improved handling for long content.

### Project issue search and filtering

**Last updated:** 23rd July, 2026

- Added multi-tag filtering and free-text search to project issues.
- Added separate status and tag filters to the project issue list.
- Added tag-based related issues.

### Project and client listing refresh

**Last updated:** 27th June, 2026

- Reworked projects and clients into refined card-based listing UIs.

### Dashboard and responsive issue-detail UI

**Last updated:** 27th June, 2026

- Refined dashboard charts using Chart.js.
- Adjusted issue-detail columns, heading sizing, and responsive action buttons.

### Roles, permissions, and timezone

**Last updated:** 27th June, 2026

- Added RBAC with Super Admin, Owner, and Developer roles and resource-level permissions.
- Set the application timezone to Dhaka.

### Demo access and ordering

**Last updated:** 28th April, 2026

- Added demo access login support.
- Updated default ordering to use `updated_at` descending.

### Nudge system

**Last updated:** 28th April, 2026

- Added an escalating, gentle-but-frequent stale-work nudge system.
- Refined visual thresholds and severity levels across follow-up commits.

### Completion tracking and daily activity

**Last updated:** 27th April, 2026

- Automatically track completion timing with `done_at` when an issue is marked Done.
- Display completion dates with a green visual treatment.
- Added a Daily Activity page with a calendar heatmap for created and completed work.

### Issue intelligence and navigation

**Last updated:** 27th April, 2026

- Added project-scoped issue tags.
- Added issue search and richer issue intelligence across Dashboard, Kanban, and Daily Activity.
- Refined issue detail, Kanban, hierarchy/tree, and nested sub-issue interfaces.

### Workspace-wide issue Quick Read

**Last updated:** 13th August, 2026

- Added one shared Quick Read modal for Project Show, Dashboard, Issues Index, Daily Activity, and Kanban.
- The modal shows issue details, tags, attachment/link previews, counts, and links to the full issue at both the top and bottom.
- Added a compact status-change control to the modal. It is available to Super Admins, project owners, Developers, and Clients; Employees cannot change issue status.
- The `issue.change_status` permission remains the server-side capability check. Migrations keep it assigned to Clients and remove it from Employees.

### Issue media and references

**Last updated:** 11th April, 2026

- Added multiple image and file attachments to issues.
- Added internal and external issue links.
- Added inline deletion for images, files, and links.
- Added image-gallery modal navigation.
- Added multi-select uploads, practical file-size limits, and datetime-prefixed filenames.

### Core application capabilities

**Last updated:** 11th April, 2026

- Multi-tenant workspaces: users only access their own clients, projects, and issues.
- Client management with create, edit, delete, and compact listing views.
- Project management with client assignment and project detail pages.
- Issue management with status updates, project reassignment, parent linking, rich descriptions, and scoped context.
- Recursive sub-issues with deeper nesting.
- Kanban board for Todo, In Progress, and Done workflows.
- Dashboard summary and activity analytics.
- Pagination across list pages; Kanban intentionally remains unpaginated.
- Custom session authentication and Super Admin user management.
- Responsive application shell, modals, SweetAlert2 confirmations, loading states, and inline validation.

## Reference commits

| Date | Commit | Change |
| --- | --- | --- |
| 11th August, 2026 | Working | Rich-text table support and copy-paste structure retention |
| 7th August, 2026 | `087ff83` | Global date formatting utility extraction and human readable dates |
| 7th August, 2026 | `3d533cf` | Tag management listing order by most recently created |
| 4th August, 2026 | `5cf318f` | Autocomplete suggestions to tag input fields on issue show page |
| 4th August, 2026 | `5ccfa56` | Latest project issue-list date-column refinement |
| 4th August, 2026 | `7c2e873` | Initial project issue-list date column |
| 4th August, 2026 | `9e5f88b` | Filterable/paginated tag management and duplicate validation |
| 4th August, 2026 | `e69127d` | Tag-management navigation and project-scoped access |
| 24th July, 2026 | `b9570a9` | Issue quick-read modal |
| 23rd July, 2026 | `5fabac4` | Pinned issues card |
| 23rd July, 2026 | `ab7d974` | TipTap editor upgrade |
| 23rd July, 2026 | `1533e27` | Multi-tag and text project-issue filters |
| 16th May, 2026 | `0df0385` | RBAC roles and permissions |
| 28th April, 2026 | `8f81d9c` | Gentle nudge system |
| 27th April, 2026 | `e200612` | Daily Activity heatmap |
| 27th April, 2026 | `1103318` | Issue tagging, search, and intelligence UX |
| 11th April, 2026 | `21e737e` | Issue images, files, and links |

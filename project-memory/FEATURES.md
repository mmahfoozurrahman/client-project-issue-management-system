# Project Feature Memory

This is a newest-first record of features and meaningful product changes, consolidated from the repository Git history. It is intended as working project memory rather than a verbatim commit log.

## Current snapshot

- **Latest commit:** `5373bd2` - feat(issues): match the project issue snapshot and order listings by latest activity.
- **Workflow:** Clients -> Projects -> Issues -> Sub-Issues.
- **Stack:** Laravel 13, PHP 8.3+, MySQL, Vue 3, Inertia.js, Vite, Bootstrap 5, SweetAlert2, TipTap, and Chart.js.

## Feature history (newest first)

### Issue library & project list ordered by latest activity

**Last updated:** 10th November, 2026

- The Issues Library page (`resources/js/Pages/Issues/Index.vue`) now reads like the Project Detail "Issue Snapshot":
  - Each row shows **Created + Updated** date badges, and the redundant **Client** and **Images** columns were dropped. Columns are now Issue / Project / Status / Sub-issues / Actions.
  - The retained Completed (done) and idle/at-risk meta lines stay in place below the dates.
- `IssueController@index` sorts top-level issues by most recent activity (`updated_at` desc, then `id` desc) instead of creation date, so freshly created or edited issues rise to the top.
- The Projects list (`resources/js/Pages/Projects/Index.vue`) is now ordered by each project's most recent issue activity:
  - `ProjectController@index` uses `withMax('issues as latest_issue_updated_at', 'updated_at')` and orders by that value descending, falling back to the project's own `created_at` then `id`. A project whose issue was just created or edited jumps to the top; projects without issues sink below.
  - Search, client eager-loading, issue counts, and permissions are unchanged.
- Removed the **Images** column from the Project Detail issue snapshot table (`resources/js/Pages/Projects/Show.vue`), so it now shows Issue / Status / Sub-issues / Action.
- Added `tests/Feature/IssueLibraryOrderingTest.php` (library ordering by most recent edit + newly created issue first) and `tests/Feature/ProjectListOrderingTest.php` (project ordering by a fresh issue, by an edited issue, and the creation-date fallback for issue-less projects).
- Rebuilt front-end assets.

### Project completion progress analytics

**Last updated:** 9th November, 2026

- Added a "Project Progress — Completion overview" panel to the Project Detail page (`resources/js/Pages/Projects/Show.vue`), placed between the pinned bookmarks and the issue list.
  - Shows the completion percentage with an "X of Y top-level issues done" caption, a segmented progress bar (Todo / In Progress / Done), and three stat tiles with counts plus each status's share of all issues.
  - Backed by a new `progress` Inertia prop computed in `ProjectController@show` with a single grouped query: `total`, `todo`, `inprogress`, `done`, and `percent` (`round(done / total * 100)`).
  - Counts **top-level issues only** (`whereNull('parent_id')`), matching both the issue list on this page and the Dashboard's existing status analytics.
  - Styles live in `resources/css/app.css` (`.project-progress-*`) because this page has no scoped style block; the tone colours line up with the Dashboard analytics.
- Added feature tests in `tests/Feature/ProjectProgressAnalyticsTest.php` (status breakdown with sub-issues excluded, and the empty-project case).

### Background auto-save for the issue edit form

**Last updated:** 9th November, 2026

- The issue edit form on the Issue Detail page (`resources/js/Pages/Issues/Show.vue`) now persists changes automatically in the background: title, description, status, project, parent issue, links, and tags are saved about 1.2s after typing stops, so editing no longer depends on pressing "Save Changes".
  - Auto-save reuses the existing `PUT /issues/{issue}` endpoint with `preserveScroll` / `preserveState` / `replace`, and adds guards: it skips the initial page load, pauses while a manual save or staged-image upload is running, queues a follow-up save when edits land mid-flight, and never fires while the (required) title or project is empty.
  - The manual "Save Changes" button remains, and is still what uploads staged images/files. Issue creation (issues and sub-issues) continues to use its normal submit button only.
  - An "Auto-save on / Saving… / Auto-saved" pill was added to the edit panel header for feedback.
  - Background saves send `autosave: true`, so the update endpoint redirects **without** the `success` flash — auto-save stays silent and only an explicit "Save Changes" submission shows the toast.
  - Fixed a related pitfall: the `props.issue.links` / `props.issue.tags` watchers used to reset `updateForm.links` / `updateForm.tag_names`, which would have wiped in-progress edits whenever an auto-save refreshed the page props. They now sync the display refs only.
- Added feature tests in `tests/Feature/IssueAutosaveTest.php` covering the silent auto-save and the still-flashing manual save.

### SEO-friendly project URLs with name slugs

**Last updated:** 9th November, 2026

- Project detail URLs now append a human-readable slug too, e.g. `/projects/26/social-media-content-planning-posting`.
  - **Routes:** `projects.show` is now `GET /projects/{project}/{slug?}`, registered after the `project-members` routes so it never shadows them. Route-model binding still resolves the `{id}`, so a stale or missing slug never breaks a link.
  - **Model:** the `Project` model exposes derived `slug` and `url` attributes (`Str::slug()` of the project name), so every serialized project carries its own detail URL.
  - Related spots were updated as well: the Issue Detail breadcrumb links to the project with its slug, and deleting an issue redirects to the slugged project URL.
- **Frontend:** added `resources/js/utils/project.js` (`projectUrl()`) and pointed the Projects-list "Open project" link plus the Project Detail filter reload at it.
- Added feature tests in `tests/Feature/ProjectSlugUrlTest.php` (slug generation, reachability with/without the slug, and project-member routes not being shadowed).

### SEO-friendly issue URLs with title and tag slugs

**Last updated:** 9th November, 2026

- Issue detail URLs now append a human-readable slug so the address bar tells you what the issue is about, e.g. `/issues/533/suura-al-kafiruun-dandapani-focus-and-awareness`.
  - **Routes:** `issues.show` is now `GET /issues/{issue}/{slug?}`, and it is registered after the more specific `/issues/*` routes so it never shadows narration, daily activity, or search-suggestion endpoints. The `{id}` still drives route-model binding, so a stale or missing slug never breaks a link.
  - **Model:** the `Issue` model exposes derived `slug` and `url` attributes (`Str::slug()` of the title plus each attached tag name), so every serialized issue carries its own detail URL. Bangla titles transliterate automatically (`সূরা আল-কাফিরূন` → `suura-al-kafiruun`).
  - Tag names are folded into the slug and sorted alphabetically for stable URLs. They are only included when the `tags` relation is already eager-loaded, which keeps serializing large issue collections free of N+1 queries.
  - Create and update redirects now land on the slugged URL, so the address bar stays descriptive right after saving.
- **Frontend:** added `resources/js/utils/issue.js` (`issueUrl()`) and pointed all 13 issue links across `IssueCard`, `IssueTree`, `IssueQuickReadModal`, `Dashboard`, `Issues/Index`, `Issues/DailyActivity`, `Issues/Show`, and `Projects/Show` at it.
- Added feature tests in `tests/Feature/IssueSlugUrlTest.php` (slug generation, Bangla transliteration, tag suffixing, and reachability with/without the slug).

### Project issue list sorted by most recently edited

**Last updated:** 9th November, 2026

- Changed the top-level issue list on the Project Detail page (`app/Http/Controllers/ProjectController.php@show`) to order by `updated_at DESC` (with an `id DESC` tiebreaker) instead of `created_at`:
  - Newly created issues (`created_at == updated_at`) now appear at the top of the list automatically.
  - Editing an issue moves it back to the top of the list, so the most recently edited issue is always listed first. Status changes also touch `updated_at`, so those rise to the top as well.
  - The `id DESC` tiebreaker keeps the ordering deterministic when multiple issues share the same timestamp.
- Added feature tests in `tests/Feature/ProjectIssueOrderingTest.php` covering both the "newly created issue is first" and "edited issue rises to the top" behaviours.
- Renamed the per-issue date badge on `Projects/Show.vue` from "Completed" to "Updated" (it renders `updated_at`), so the label matches the last-edited sort order and stays consistent with the "Updated" label already used in `IssueQuickReadModal.vue`.

### Issue Show page responsive breakpoint fixes (992–1199px)

**Last updated:** 9th November, 2026

- Fixed a broken layout on the Issue Show page (`resources/js/Pages/Issues/Show.vue`) that only appeared in the `992px–1199px` viewport band:
  - **Hero overflow fix:** `resources/css/app.css` switches `.hero-panel` to `flex-direction: column` at `max-width: 1199px`, which turned `.issue-hero-copy`'s `flex: 1 1 32rem` into a ~`512px` tall column and stretched the hero card with a large empty area below the title. The Show page only reset this at `max-width: 991.98px`, leaving the `992–1199px` range broken. Added a new `@media (max-width: 1199.98px)` block that resets `.issue-hero-copy` to `flex: initial` and makes `.issue-hero-actions` full-width and left-aligned, aligning the page breakpoint with the global shell breakpoint.
  - **Grid stacking fix:** `col-xl-7` / `col-xl-5` had no base `col-12`, so below `1200px` both grid columns stayed side-by-side at auto width instead of stacking full-width. Updated them to `col-12 col-xl-7` and `col-12 col-xl-5`.
- Verified with `npm run build`.

### Collapsible left sidebar toggle

**Last updated:** 9th November, 2026

- Added a desktop sidebar show/hide toggle in the shared `AppLayout.vue` shell (`resources/js/Layouts/AppLayout.vue`), so it applies to every authenticated page including `AdminLayout` pages:
  - New topbar button (visible `≥1200px`) collapses the sidebar with a smooth slide/fade and lets the main content expand to full width; clicking again restores it.
  - Collapse state is persisted in `localStorage` (`sidebar-collapsed`) so it survives navigation and page reloads.
  - Uses `PanelLeftClose` / `PanelLeftOpen` icons with `aria-label`, `title`, and `aria-expanded` for accessibility.
- Fixed a pre-existing breakpoint gap where the sidebar's off-canvas breakpoint (`1199px`) did not match the mobile toggle visibility: switched the hamburger, backdrop, and close controls from `d-lg-none` to `d-xl-none` so the drawer toggle is available across the whole `≤1199px` range (including the previous dead zone of `992px–1199px`).
- Styling added in `resources/css/app.css` (`.app-shell` grid transition, `.sidebar-collapsed` rules, and the `.topbar-heading` wrapper).

### Feed-style vertical scroll issue image modal & project workflow process

**Last updated:** 21st September, 2026

- Redesigned issue image popup modal on the Issue Show page ([Show.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Issues/Show.vue)):
  - Upgraded modal width from `modal-lg` to `.modal-xl` (`1020px` max-width in [app.css](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/css/app.css) with responsive breakpoint rules).
  - Replaced cramped horizontal pagination controls (side Prev/Next buttons) with full-width, Facebook feed single-image style cards.
  - Implemented smooth vertical scrolling for issues with multiple images (`max-height: calc(85vh - 130px)`), allowing natural mouse-wheel and trackpad exploration.
  - Added sticky quick-jump pill navigation (`#1`, `#2`, `#3`...) with active pill highlighting tracked during vertical scrolling.
  - Thumbnail clicks in the gallery grid smoothly open the modal directly scrolled to the selected image.
  - Each image card includes a clean metadata header: image counter (`1 / 6`), filename tooltip, file size badge, and 1-click action buttons: ↗ **Full Size** (new tab), ⬇ **Download**, and **Delete** (permission-aware).
  - Resolved flex-shrink collapse via `flex: 0 0 auto !important` and block image layout to guarantee natural proportional heights for all screenshots without cropping or distortion.
- Established project-wide guidance and verification workflow in [project-memory/WORKFLOW.md](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/project-memory/WORKFLOW.md) and linked it in [CODE_STRUCTURE.md](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/project-memory/CODE_STRUCTURE.md).

### Related issues combined tag matching (AND-match)

**Last updated:** 20th September, 2026

- Updated related issues query on the Issue Detail page (`IssueController@show`):
  - Changed tag matching from disjunction (`whereIn`, matching any tag) to conjunction (`whereHas` for each tag, matching all combined tags).
  - An issue with multiple tags (e.g. `[46. Developer Mindsets, RabbilHasan]`) will now only display related issues that possess all of those tags combined, rather than issues with only one of them.
  - Selected and returned `project_id` in the matching issues payload to ensure tag links route correctly.
- Updated empty state copy on `Issues/Show.vue` from *"share one or more of these tags"* to *"share all of these tags"*.
- Added automated feature tests in `tests/Feature/IssueRelatedTagsTest.php`.

### Clipboard screenshot paste (Ctrl+V) as JPEG & staged image upload

**Last updated:** 20th September, 2026

- Added clipboard paste listener (`Ctrl+V`) across:
  - **Issue Detail Page** ([Show.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Issues/Show.vue)) for editing and sub-issue creation.
  - **Issues Library Page** ([Index.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Issues/Index.vue)) in the "Create Issue" modal.
  - **Project Details Page** ([Projects/Show.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Projects/Show.vue)) in the "Create Issue" modal.
- Extracted shared image helpers to [resources/js/utils/image.js](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/utils/image.js) with automatic canvas re-encoding to convert clipboard screenshots into compressed, standard JPEG format (`.jpg`, `image/jpeg` MIME type, 0.92 quality) with solid white background to prevent black boxes on transparent PNGs.
- Created interactive staged images preview grid:
  - Generates instant thumbnail previews via `URL.createObjectURL`.
  - Shows file name, formatted file size, and `Pasted (JPEG)` indicator pills.
  - Per-image remove (`×`) buttons to discard unwanted screenshots before uploading.
  - Drag-and-drop and file picker integration.
- Added instant 1-click **"Upload Now"** button and dedicated backend route `POST /issues/{issue}/images` (`IssueController@uploadImages`) on the Issue Show page.
- Submitting issue forms seamlessly uploads all staged images alongside the issue.

### Debounced live search suggestions with description matching & 2-character rule

**Last updated:** 12th September, 2026

- Introduced a dedicated, high-performance search suggestion endpoint: `GET /issues/search-suggestions`.
- Queries `title`, `description`, and `links` with top-8 result limit (`LIMIT 8`) and project/status/tag scoping.
- Implemented clean plain-text snippet extraction for description matches, displaying context around the matched term in the dropdown.
- Enforced a minimum 2-character requirement (`minLength: 2`) and 300ms debounce to prevent premature or single-character database queries.
- Built reusable `<IssueSearchAutocomplete.vue>` component with `AbortController` cancellation for rapid typing, keyboard navigation (`Arrow Up/Down`, `Enter`, `Esc`), clear match-type badges (`In description`, `Link`, project pill), and reliable empty/error state handling.
- Integrated across both the Issues Library ([Issues/Index.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Issues/Index.vue)) and the Project Details page ([Projects/Show.vue](file:///d:/Development/Projects/LaravelVue/5.%20LaravelInertiaVue/3.%20issue-listing-2/resources/js/Pages/Projects/Show.vue)).

### Gemini Bengali TTS narration system & reference alignment

**Last updated:** 8th September, 2026

- Implemented issue audio narration powered by Google Gemini TTS (`gemini-2.5-flash-preview-tts`) with natural Bengali speech synthesis (`bn-IN`) and prebuilt voices (`Kore`, `Puck`, `Fenrir`, etc.).
- Integrated speech naturalization in `IssueNarrationService`:
  - Intelligent sequential narration for flowchart and architecture arrow diagrams (`↓, ⬇, ▼, ->, -->, =>, ➔, ➜, |, ├──, └──`).
  - Terminal CLI command recognition and speech formatting (`php`, `composer`, `npm`, `npx`, `git`, `artisan`, `docker`, etc.).
  - Code block speechification with operator pronunciation and long/short snippet summarization.
  - Complete heading tag traversal (`h1` through `h6`).
- Direct synchronous generation with execution timeout safety (`ISSUE_NARRATION_MAX_EXECUTION_SECONDS`).
- Telemetry & usage tracking: captures prompt tokens, completion audio tokens, total tokens from Gemini `usageMetadata`, and synthesis duration.
- Added isolated test generator (`generateGeminiTest`) and `LessonNarrationService` compatibility wrapper.
- Client audio playback in Quick Read modal (`IssueQuickReadModal.vue`) via `useIssueNarration.js`:
  - Interactive seekbar scrubber slider matching reference UI with smooth ±5s skipping and seeking.
  - Server-side byte-range response (`HTTP 206 Partial Content`, `Accept-Ranges: bytes`) via `BinaryFileResponse` in `IssueNarrationController.php`, enabling instant audio scrubbing without resetting to 0:00.
  - Spacebar / Ctrl+P keyboard shortcut to toggle audio Play/Pause without taking hands off the keyboard.
  - Left / Right arrow keys (`←` / `→`) to scrub audio 5 seconds backward/forward.
  - Formatted Bengali numerals for current timestamp (`০:০৬`) and total track duration (`৬:৫৪`).
  - Styled with green circular thumb handle, mint progress track, and multi-track indicator pill.
  - Auto-pausing, resumption, and rate-limit error handling.

### Responsive pinned-issues carousel

**Last updated:** 13th August, 2026

- Changed the Project Show page's pinned-issues card grid into a single-row horizontal carousel, preventing pinned issues from wrapping onto a second row.
- Added accessible previous/next controls that appear only when the card rail has additional content to scroll; touch, trackpad, and standard horizontal scrolling remain supported.
- Added desktop, tablet, and mobile card sizing plus layout containment so carousel overflow stays inside the rail and cannot widen the project page.

### Workspace-wide clickable issue tags

**Last updated:** 13th August, 2026

- Made displayed issue tags clickable throughout the workspace, including the Dashboard, issue list, project issue lists, Kanban, Daily Activity, Quick Read modal, related-issue panel, and nested issue tree.
- Each tag opens the issue list using the existing scoped filters: `/issues?tag_id={id}&project_id={project_id}`.
- Tag-management controls and editable tag inputs remain unchanged; the Tag Management page already links its issue-count pill to the filtered list.

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

**Last updated:** 21st September, 2026

- Added one shared Quick Read modal for Project Show, Dashboard, Issues Index, Daily Activity, Kanban, and Issue Show (both Related Issues and Nested Child Issues tree).
- The modal shows issue details, tags, attachment/link previews, counts, audio narration player, and links to the full issue at both the top and bottom.
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

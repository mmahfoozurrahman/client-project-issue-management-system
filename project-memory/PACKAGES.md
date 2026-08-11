# Composer and npm Packages

This list records the direct packages declared in `composer.json` and `package.json`. Versions are the configured version ranges; resolved transitive dependencies are recorded in `composer.lock` and `package-lock.json`.

## PHP / Composer packages

### Runtime dependencies

| Package | Version | Used for |
| --- | --- | --- |
| `php` | `^8.3` | Required PHP runtime. |
| `laravel/framework` | `^13.0` | Backend framework: routing, Eloquent, migrations, validation, storage, queues, sessions, and application services. |
| `inertiajs/inertia-laravel` | `^3.0` | Laravel adapter for server-driven Inertia responses to Vue pages. |
| `tightenco/ziggy` | `^2.6` | Makes named Laravel routes available to the Vue frontend. |
| `laravel/tinker` | `^3.0` | Interactive Laravel/PHP console for development and data inspection. |

### Development dependencies

| Package | Version | Used for |
| --- | --- | --- |
| `fakerphp/faker` | `^1.23` | Generates realistic fake data in tests and factories. |
| `laravel/pail` | `^1.2.5` | Live application-log viewer for local development. |
| `laravel/pint` | `^1.27` | PHP code-style formatter. |
| `mockery/mockery` | `^1.6` | Test doubles and mocks. |
| `nunomaduro/collision` | `^8.6` | Improved command-line exception output. |
| `phpunit/phpunit` | `^12.5.12` | PHP/Laravel test runner. |

## JavaScript / npm packages

### Application dependencies

| Package | Version | Used for |
| --- | --- | --- |
| `vue` | `^3.5.31` | UI framework for all Inertia pages, layouts, and reusable components. |
| `@inertiajs/vue3` | `^3.0.1` | Vue adapter for Inertia navigation, forms, and server props. |
| `ziggy-js` | `^2.6.2` | Client-side access to Laravel named routes. |
| `bootstrap` | `^5.3.8` | Base responsive layout and UI styling utilities. |
| `@popperjs/core` | `^2.11.8` | Positioning engine used by Bootstrap popovers, dropdowns, and tooltips. |
| `sweetalert2` | `^11.26.24` | Confirmation dialogs and polished user notifications. |
| `lucide-vue-next` | `^1.0.0` | Vue icon library used throughout the interface. |
| `chart.js` | `^4.5.1` | Dashboard analytics and activity charts. |
| `@tiptap/vue-3` | `^3.28.0` | Vue integration for the rich-text editor. |
| `@tiptap/starter-kit` | `^3.28.0` | Core TipTap editor nodes, marks, and editing behaviour. |
| `@tiptap/extension-highlight` | `^3.28.0` | Text highlighting in rich-text descriptions. |
| `@tiptap/extension-link` | `^3.28.0` | Link handling in the rich-text editor. |
| `@tiptap/extension-placeholder` | `^3.28.0` | Placeholder text for empty editor content. |
| `@tiptap/extension-underline` | `^3.28.0` | Underline formatting in the editor. |
| `@tiptap/extension-table` | `^3.28.0` | Table creation, row/cell manipulation, and tabular copy-paste handling. |
| `@vitejs/plugin-vue` | `^6.0.5` | Vite support for Vue single-file components. |

### Development/build dependencies

| Package | Version | Used for |
| --- | --- | --- |
| `vite` | `^8.0.0` | Development server and production frontend bundler. |
| `laravel-vite-plugin` | `^3.0.0` | Connects Vite’s build process and hot-reload flow with Laravel. |
| `tailwindcss` | `^4.0.0` | Utility-first CSS framework available to the frontend build. |
| `@tailwindcss/vite` | `^4.0.0` | Tailwind CSS integration for Vite. |
| `axios` | `>=1.11.0 <=1.14.0` | HTTP client available for frontend requests. |
| `concurrently` | `^9.0.1` | Runs the Laravel server, queue listener, log viewer, and Vite together through Composer’s `dev` script. |

## Package scripts

| Command | What it does |
| --- | --- |
| `composer setup` | Installs PHP and JS dependencies, creates `.env` when needed, generates the app key, migrates the database, and builds frontend assets. |
| `composer dev` | Starts Laravel, the queue listener, Pail logs, and Vite concurrently. |
| `composer test` | Clears configuration cache and runs the Laravel test suite. |
| `npm run dev` | Starts the Vite development server. |
| `npm run build` | Creates the production Vite asset build. |

## Package boundaries

- Laravel and its Composer packages implement the server, database, authorization, validation, sessions, and Inertia responses.
- Vue, Inertia Vue, and Ziggy implement the interactive client application and named-route access.
- Bootstrap, Popper, Lucide, SweetAlert2, Chart.js, and TipTap provide the visual and interaction features layered on top of Vue.
- Vite and Laravel Vite Plugin build and serve the frontend assets.

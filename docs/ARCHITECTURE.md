# Project structure

## Public entry points

All browser-accessible PHP routes and the CSS compatibility entry point are in `frontend/`. Configure Apache's `DocumentRoot` to this folder in production. During local XAMPP migration, the root `.htaccess` forwards existing routes to `frontend/` and blocks direct access to backend source folders.

## Frontend

Public page templates remain at the project root while the application is migrated incrementally. Shared browser assets belong in `frontend/assets/`. The source stylesheet is `frontend/assets/css/app.css`; root `style.css` is a compatibility entry point for existing pages.

Shared modal markup is in `frontend/templates/modals.php`. The root `view.php` is a compatibility entry point for pages that still include it.

## Backend

- `backend/config/database.php` — sessions, environment configuration, database connection, payment constants.
- `backend/api/` — request handlers for authentication, comments, model actions, PromptPay QR, and admin actions.

The root-level PHP action files are intentionally thin compatibility routes. Existing forms and JavaScript may keep using their current URLs while new work imports or routes to `backend/api/` directly. This permits a safe, staged migration without breaking bookmarks or client-side requests.

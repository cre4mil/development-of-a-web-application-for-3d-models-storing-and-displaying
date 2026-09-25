# 3D Gallery — 3D model storage and viewer

A Sketchfab-style web application: upload 3D models, browse them in a searchable gallery, inspect them
in the browser (orbit, wireframe, material channels, VR/fullscreen), like / save / comment / follow,
sell models with PromptPay slip verification, and pay creators out.

PHP 8.1+ · MySQL/MariaDB · Bootstrap 5 · Three.js · PHPUnit · SonarQube

## Run locally (XAMPP)

1. Copy the project to `htdocs`, start Apache and MySQL.
2. Create the database and tables:
   ```bash
   mysql -u root -e "CREATE DATABASE db_3dmodels CHARACTER SET utf8mb4"
   mysql -u root db_3dmodels < database/schema.sql
   ```
   Existing installs only need the files in `database/migrations/` they have not applied yet.
3. `cp .env.example .env` and adjust DB credentials, payment account and (optionally) `BLENDER_PATH`.
4. Open `http://localhost/<project-folder>/`.

`composer install` is optional for running the site (a built-in autoloader is used) but required for tests.

## Tests and coverage

```bash
composer test            # fast run
composer test-coverage   # PHPUnit + coverage.xml (project-relative paths for Sonar)
```

The suite uses an in-memory SQLite database, so it needs no MySQL server. All PHP under `backend/` and
`frontend/` — controllers, repositories, services, templates and entry scripts — is covered.

### SonarQube

```bash
docker compose --profile core up -d                                   # SonarQube on :9000
composer test-coverage
docker compose --profile core --profile scanner run --rm --no-deps sonar-scanner
```

`LOCAL_SONAR_TOKEN` comes from `.env`. Browser scripts in `frontend/assets/` are excluded from the
coverage figure (they are not PHP); everything else is measured. CI runs the same steps against
SonarCloud (`.github/workflows/sonar.yml`).

## Project layout

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the request flow, folder map and conventions.

## Security notes

* `.env` is git-ignored; never commit tokens. Rotate any token that was committed in the past.
* State-changing endpoints require a POST, a session and a CSRF token.
* Uploaded files are stored under `frontend/uploads/`, where scripts are never executed
  (`.htaccess`). Payment slips are stored there too — keep the directory listing disabled.
* Paid models can be *viewed* by anyone (the browser needs the file to render it); only downloads are
  gated by purchase.

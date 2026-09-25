# Architecture

## Request flow

```
browser ──► frontend/<page>.php ──► backend/bootstrap.php ──► Kernel::run(Controller, method)
                                                                    │
            Response (HTML / JSON / file / redirect) ◄── Controller ◄┘
                                                          │   │
                                            Repositories ─┘   └─ View::page(template)
                                            Services
```

* Every public script in `frontend/` is a two-line **entry point**: it requires `backend/bootstrap.php`
  and calls `Kernel::run(SomeController::class, 'method')`. Scripts contain no logic.
* `Kernel` builds a `Request` (query, post, files, session), calls the controller and sends the
  `Response`. Uncaught exceptions are logged and become a generic 500.
* Controllers never call `exit`, `header()` or `echo`; they return a `Response` object, which is what
  makes every page and endpoint testable in-process.

## Folder map

| Path | Purpose |
| --- | --- |
| `frontend/*.php` | Page entry points (`index`, `model`, `profile`, `like`, `orders`, `creator_earnings`, `admin*`, `download`, `login`, `register`, `logout`) |
| `frontend/api/*.php` | JSON endpoints (`models`, `social`, `orders`, `admin`), each dispatching on an `action` parameter |
| `frontend/templates/` | PHP templates: `layout.php`, `pages/`, `partials/` |
| `frontend/assets/` | `css/app.css` (design system), `js/*.js` (browser code), `img/` |
| `frontend/uploads/` | Uploaded models, thumbnails, `slips/`, `payout_slips/` (git-ignored) |
| `backend/http/` | `Request`, `Response`, `Session`, `View`, `Kernel`, `HttpException` |
| `backend/controllers/` | One class per area: Home, Model, Social, Order, Profile, Earnings, Admin, Auth |
| `backend/repositories/` | All SQL: `CatalogRepository`, `SocialRepository`, `AccountRepository`, `CommerceQueries`, `Pdo*Repository` (orders/payouts) |
| `backend/services/` | Business rules: `OrderService`, `PayoutService`, `ModelFiles`, `ModelConverter` (Blender), `SlipVerifier` (SlipOK), `PromptPay`, `Security`, … |
| `backend/support/` | `Config` (env), `Database` (PDO), `Format`, `Bootstrap` |
| `backend/scripts/convert.py` | Blender script used by `ModelConverter` |
| `database/` | `schema.sql` (fresh install) and `migrations/` (existing installs) |
| `tests/` | PHPUnit suite (SQLite in memory, no MySQL needed) |
| `tools/` | Dev helpers not shipped to the web root |

## Conventions

* **Controllers** – page methods return `View::page(...)` or a redirect; JSON actions are listed in
  `ACTIONS` and abort with `$this->abort()/abortUnless()/guard()` (turned into `{ok:false,error,message}`).
  Every state-changing action calls `guard()` (POST + signed in + CSRF; `admin: true` for admin only).
* **CSRF** – the token lives in `<meta name="csrf-token">`; `App.api()` sends it as `X-CSRF-Token`.
* **Templates** – escape with `e()`, format money with `money()`, build asset URLs with `asset()`,
  include partials with `partial('name', [...])`.
* **Frontend JS** – no build step. `core.js` provides `App.api`, toasts, `App.confirm`, and delegated
  `data-action="…"` handlers; `viewer.js` is the Three.js viewer (libraries load on demand);
  page scripts (`gallery.js`, `model.js`, `upload.js`, `admin.js`, `earnings.js`) extend `App.actions`.
* **SQL** – must run on both MySQL (production) and SQLite (tests): no `RAND()`, `DATE_SUB`, `FIELD`,
  `INSERT IGNORE` directly (use `Database::insertIgnore()`), positional `?` parameters only.

## Adding things

* **A page**: create `frontend/foo.php` (two lines), a controller method returning `View::page($request,
  'pages/foo', [...])`, the template, and a test in `tests/Controllers`.
* **An API action**: add `'name' => 'method'` to the controller's `ACTIONS`, write the method with
  `$this->guard($request)` first, and test success plus each abort path.
* **A table/column**: add `database/migrations/NNN_name.sql`, update `database/schema.sql` and
  `tests/Support/schema.sql`.

## Business rules worth knowing

* Platform fee is read from `platform_settings.platform_fee_pct` (default 10 %); creators can withdraw
  once their wallet reaches 300 THB. Orders are approved by an admin or automatically by SlipOK.
* A model that has been sold cannot be deleted (it can be hidden); the same goes for users with
  purchase/sale/payout history.
* Views are counted once per visitor session and never for the owner.
* Uploads accept `glb gltf obj fbx stl ply dae 3ds`. When `BLENDER_PATH` is set the upload is also
  converted to glTF/GLB/OBJ/USDZ; the viewer prefers the GLB because textures are embedded.

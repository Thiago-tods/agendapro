# AgendaPro

[Português](README.md) · [English](README.en.md)

Multi-tenant appointment scheduling and management platform for service businesses: clinics, barbershops, consultancies, studios and workshops.

> Status: MVP in development. Not production-ready yet (see [Known limitations](#known-limitations)).

## The problem

Service businesses often run their schedule on spreadsheets, notebooks or chat messages. That leads to double bookings, appointments outside business hours, customers without history and little visibility into revenue. AgendaPro brings the schedule, customers, team and service catalog together, and prevents scheduling conflicts at the database level.

## Features

- **Visual calendar** with day, week and month views, filtered by professional and service.
  - Blocks are proportional to duration and show the buffer after each appointment.
  - Shows business hours, blocked periods and each appointment's status, always as text and never by color alone.
- **Appointments:** create, confirm, reschedule, check in, complete, mark no-show and cancel (with a reason). Every change is recorded in the history.
- **Availability rules:**
  - Company hours and per-professional working hours.
  - Date exceptions and blocked periods.
  - Duration plus buffer, using half-open `[start, end)` intervals.
- **Conflict protection:** a PostgreSQL `EXCLUDE` constraint plus row locking inside the transaction. Two overlapping appointments for the same professional are rejected, even under concurrent requests.
- **Dashboard** with operational and business indicators computed from real data.
- **Customers:** search, filters, profile, visit history and archiving.
- **Professionals:** profile, services performed, working hours and activation.
- **Services:** catalog with price, duration, buffer, category, color and assigned professionals.
- **Settings:** company details, business hours, team roles (owner, admin, receptionist, professional), profile and password change.
- **Multi-tenancy:** each company is isolated. Access is checked against the user's membership on every request; the URL alone grants nothing.

## Screenshots

<!-- Add real screenshots under docs/screenshots/ and replace the items below. -->

| Screen | Screenshot |
|---|---|
| Dashboard | _to add: `docs/screenshots/painel.png`_ |
| Calendar (week) | _to add: `docs/screenshots/agenda-semana.png`_ |
| Appointment details | _to add: `docs/screenshots/agendamento.png`_ |
| Customers | _to add: `docs/screenshots/clientes.png`_ |
| Mobile (day view) | _to add: `docs/screenshots/mobile-agenda.png`_ |

## Stack

| Layer | Technologies |
|---|---|
| Backend | PHP 8.4, Laravel 13, Laravel Sanctum (cookie-based SPA auth), PHPUnit |
| Database | PostgreSQL 18 (`btree_gist` extension) |
| Frontend | Vue 3, TypeScript, Vite, Vue Router, Pinia, Tailwind CSS 4, Vitest |

## Architecture

A modular monolith split into two apps:

```text
backend/    Laravel REST API under /api/v1, organized by business module
  app/Modules/{Auth, Companies, Customers, Professionals, Services,
               Availability, Appointments, Dashboard}
frontend/   Vue SPA, also organized by module
  src/modules/{auth, dashboard, appointments, customers, professionals,
               services, settings, account}
```

- **Authentication:** Sanctum in SPA mode, with an HttpOnly session cookie and CSRF protection. No token is stored in the browser.
- **Tenancy:** `/api/v1/companies/{company}/...` routes go through the `company.member` middleware and use scoped bindings. Composite `(company_id, id)` foreign keys block cross-company references.
- **Authorization:** policies and Form Requests in the backend. The frontend only hides actions, based on the permissions the API sends.
- **Time:** stored in UTC and displayed in the company's timezone. The API sends local times, so the browser does no timezone conversion.

## Requirements

- PHP 8.4 with the `pdo_pgsql`, `mbstring`, `openssl` and `intl` extensions
- Composer 2
- PostgreSQL 18, with permission to create the `btree_gist` extension
- Node.js `^22.18` or `>=24.12`, with npm

## Database setup

Create one database for development and one for tests:

```sql
CREATE DATABASE agendapro;
CREATE DATABASE agendapro_test;
```

The migrations create the `btree_gist` extension; the database user needs permission to do so.

## Running

### Backend

```bash
cd backend
composer install
cp .env.example .env          # PowerShell: Copy-Item .env.example .env
# Edit .env: DB_USERNAME and DB_PASSWORD
php artisan key:generate
php artisan migrate --seed    # --seed adds demo data
php artisan serve             # http://localhost:8000
```

### Frontend

```bash
cd frontend
npm ci
npm run dev                   # http://localhost:5173
```

Vite proxies `/api` and `/sanctum` to `http://localhost:8000`, so the browser talks to a single origin.

### Demo users

The seeder creates the company "Studio Aurora" with fictional data. All users have the password `password` and are **for local development only**:

| Role | E-mail |
|---|---|
| Owner | `owner@agendapro.test` |
| Admin | `admin@agendapro.test` |
| Receptionist | `recepcao@agendapro.test` |
| Professional | `profissional@agendapro.test` |

## Tests

```bash
# Backend: uses the agendapro_test database (see phpunit.xml)
cd backend
php artisan test
vendor/bin/pint --test        # code style

# Frontend
cd frontend
npx vitest run
npm run build                 # type-check and production build
```

## Known limitations

- No Playwright end-to-end tests, and no ESLint or Prettier configured in the frontend.
- Date exceptions and blocked periods exist in the API but have no screen yet.
- No e-mail sending:
  - New members get an initial password set by an admin, with no forced change on first login.
  - Adding an e-mail that already has an account reveals that the account exists.
- The check-in button appears outside the allowed window; the API rejects it and shows the message.
- Calendar availability uses per-day, per-professional queries, and search uses `ilike` without a trigram index. Both may be slow at large volumes.
- No phone input mask. Professionals are not linked to user accounts.
- No payments, subscriptions, external calendar sync or public booking portal.

## License

Not defined yet.

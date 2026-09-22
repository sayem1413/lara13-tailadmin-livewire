# Laravel Admin Starter

A reusable Laravel + Livewire + TailAdmin admin dashboard starter: authentication, roles &
permissions, and a common admin shell, meant to be cloned into new projects and extended.

## Setup

```bash
git clone <this-repo> your-project-name
cd your-project-name

composer run setup   # composer install, .env, app key, migrate, storage:link, npm install, npm build
npm run build         # re-run any time frontend assets change; `npm run dev` for a watching dev server

php artisan migrate --seed   # seeds default roles/permissions and a super-admin user
```

Then start the app with `composer run dev` (serves the app, queue listener, and Vite dev server
together) or `php artisan serve`.

## Default admin login

| Email | Password |
| --- | --- |
| `admin@example.com` | `password` |

Set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before seeding to change these.

## Renaming for a new project

- Update `APP_NAME` in `.env`.
- Update `composer.json`'s `name`/`description`.
- Adjust `config/menu.php` and `config/search.php` as you add project-specific modules.

# PROJECT LANTERN V2

Independent rebuild staging branch of Project Lantern, retaining the PHP file manager dashboard and CSS/JS design.

## Local setup
1. Install Docker Desktop.
2. Copy `.env.example` to `.env`, replace both passwords with strong unique values (no spaces or `#`).
3. Run `docker compose up -d --build`.
4. Run `docker compose exec -T mariadb mariadb -u root -p < database-init/001-schema.sql` in a compatible shell, or import the SQL file via local phpMyAdmin (`docker compose --profile admin up -d phpmyadmin`).
5. Open http://localhost:8080/auth.php and register an account.

## Storage
V2 uses separately named Docker volumes `lantern_v2_db` and `lantern_v2_files` so the V1 database and uploads are not reused. Never use `docker compose down -v` unless you intend to delete data.

## Security
Uploaded files are stored outside Apache's web root. Authenticated previews use `preview.php`; downloads require a logged-in user. Database credentials come from local environment variables. Do not commit `.env`.
Keep this service on localhost/private network until authorization, file operations, authentication rate limits, backup/restore, and deployment security have been tested.

## Status
Development preview; not a completed security audit or a production-certified application.

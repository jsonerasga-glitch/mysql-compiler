# MySQL Client

A lightweight, browser-based MySQL client built with plain PHP and vanilla JS —
connect to any MySQL server, browse its schema, and run SQL from a CodeMirror
editor with a DBeaver-style result grid.

## Features

- **Login is a connection** — log in with a MySQL host/username/password (no
  separate app account); the credentials double as your session and are saved
  as a connection profile for next time.
- **Multiple saved connections**, scoped per logged-in user, manageable from
  the sidebar (add / edit / delete / test).
- **Schema tree** — browse databases → tables/views → columns, with primary
  keys flagged; double-click a table to insert `database.table` into the
  editor.
- **SQL editor** (CodeMirror, MySQL mode) with `Ctrl+Enter` to run, and a
  "highlight statement at cursor" command (`Ctrl+Alt+H`) for running one
  statement out of a multi-statement script.
- **Result grid with infinite scroll** — `SELECT`/`WITH` queries are fetched
  200 rows at a time; scrolling near the bottom of the grid loads the next
  page automatically, so large result sets don't have to be pulled all at
  once.
- **Query log** — every execution (success or error) is recorded in the app's
  own database, including timing, row/affected counts, and the client IP.

## Requirements

- PHP 8+ with the `pdo_mysql` extension
- A MySQL server for the app's own storage (connections + query log)
- Access to whatever MySQL server(s) you want to query

## Setup

1. **Configure the app database** in `config/database.php`:

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', 3306);
   define('DB_DATABASE', 'sql_client_app');
   define('DB_USERNAME', 'your_user');
   define('DB_PASSWORD', 'your_password');
   ```

2. **Set an encryption key** in `config/config.php` (used to encrypt saved
   connection passwords at rest):

   ```php
   define('ENCRYPTION_KEY', 'a-random-32-byte-secret-key');
   ```

3. **Create the schema**:

   ```bash
   php database/migrate.php
   ```

   This creates the `sql_client_app` database and its `connections` /
   `query_log` tables (and applies incremental upgrades if they already
   exist from an older version).

4. **Run it** with PHP's built-in server, or point Apache/Nginx at the
   project root:

   ```bash
   php -S 127.0.0.1:8000
   ```

5. Open `http://127.0.0.1:8000/login.php` and connect with the host,
   username, and password of the MySQL server you want to work with.

## Project structure

```
api/            JSON endpoints (login, connections, schema, query, session, logout)
includes/       Shared PHP helpers (auth, db, crypto, mysql client, query log, http/JSON)
config/         App database credentials and encryption key (not committed with real secrets)
database/       schema.sql + migrate.php for the app's own database
assets/js/      Frontend: app.js (main UI logic), login.js
assets/css/     Styling
assets/vendor/  Bootstrap, Bootstrap Icons, CodeMirror
index.php       Main client UI
login.php       Login page
```

## How pagination works

`api/query.php` accepts `offset` and `limit` (default 200, max 500). For
`SELECT`/`WITH` statements, the query is wrapped as a derived table and one
extra row beyond `limit` is fetched to cheaply determine `has_more` without a
separate `COUNT(*)`. `SHOW` / `DESCRIBE` / `EXPLAIN` results aren't paginated
(they can't be used as a derived table) and are returned in full. The
frontend (`assets/js/app.js`) fetches page 1 on run, then listens for scroll
events on the result grid to fetch and append subsequent pages, backfilling
automatically if the first page doesn't fill the visible area.

## Security notes

- Saved connection passwords are encrypted at rest (AES, via
  `includes/crypto.php`) using `ENCRYPTION_KEY`.
- Connections are scoped per `owner_username`, so users can only see and
  manage their own saved connections.
- All target-database queries run through PDO prepared statements or
  parameter binding where user input is involved; the SQL you write in the
  editor is executed as-is against your own target connection.

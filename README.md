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

## Install on a PC with Docker (recommended, no programming needed)

These steps are for a Windows PC that **already has MySQL installed**. You
don't need PHP, a web server, or any programming knowledge — Docker runs the
app for you, and it uses the MySQL that's already on the PC.

### Step 1 — Install Docker Desktop (one time only)

1. Download Docker Desktop from <https://www.docker.com/products/docker-desktop/>
   and run the installer. Keep the default options.
2. Restart the PC if the installer asks you to.
3. Open **Docker Desktop** from the Start menu and wait until the bottom-left
   corner says **Engine running**. (You can skip signing in.)

> Tip: in Docker Desktop → **Settings → General**, tick **Start Docker Desktop
> when you sign in to your computer** so the app comes back after a reboot.

### Step 2 — Copy the app to the PC

Copy this whole project folder to the PC, for example to
`C:\MySQL-Web-Client`. (From GitHub: **Code → Download ZIP**, then right-click
the ZIP → **Extract All…**)

### Step 3 — Start it

1. Open the folder and double-click **`start.bat`**.
2. The first time, Notepad opens a settings file called `.env`. Change these
   two lines to the MySQL username and password of this PC's MySQL
   (usually the `root` account you created when installing MySQL):

   ```
   DB_USERNAME=root
   DB_PASSWORD=your-mysql-password
   ```

   Leave everything else as it is. **Save** (Ctrl+S) and **close** Notepad.
3. Wait while it starts. The very first start downloads and builds
   everything, so it can take a few minutes; later starts take seconds.
4. Your browser opens **<http://localhost:8080>** automatically.

The app creates its own database (`sql_client_app`) in MySQL on first start —
you don't need to create anything by hand.

### Step 4 — Log in

On the login page enter:

| Field    | What to type                                   |
| -------- | ---------------------------------------------- |
| Host     | `localhost` (the MySQL on this PC)             |
| Port     | `3306`                                         |
| Username | your MySQL username, e.g. `root`               |
| Password | your MySQL password                            |

To connect to a MySQL server on **another** computer, type that computer's
IP address (e.g. `192.168.1.20`) as the Host instead.

### Everyday use

| To…                         | Do this                                            |
| --------------------------- | -------------------------------------------------- |
| Open the app                | Go to <http://localhost:8080> in any browser       |
| Start it                    | Double-click `start.bat`                           |
| Stop it                     | Double-click `stop.bat`                            |
| See error messages          | Double-click `logs.bat`                            |
| Change settings             | Edit `.env` in Notepad, then run `start.bat` again |
| Allow phones / other PCs    | Double-click `allow-network-access.bat` (once)     |

Once started, the app keeps running in the background and starts again by
itself whenever Docker Desktop starts, until you run `stop.bat`.

### Use it from a phone or another PC

Windows Firewall blocks other devices by default. To allow them, double-click
**`allow-network-access.bat`** once and click **Yes** when Windows asks for
permission. It opens the app's port in the firewall and shows the address to
type on the phone, e.g. `http://192.168.1.2:8080`. The phone must be on the
same Wi-Fi/network as this PC.

If you change `APP_PORT` in `.env` later, run `allow-network-access.bat`
again.

### Troubleshooting

- **"Docker Desktop is not running"** — open Docker Desktop, wait for
  *Engine running*, then double-click `start.bat` again.
- **The page doesn't open / keeps loading** — the app may still be waiting
  for MySQL. Double-click `logs.bat`:
  - `Access denied for user` → the username or password in `.env` is
    wrong. Fix it in Notepad, save, and run `start.bat` again.
  - `Connection refused` / `Could not reach the MySQL server` → make sure
    the MySQL service is running (Start menu → **Services** → *MySQL80* →
    Start).
  - `Host '...' is not allowed to connect` → MySQL only accepts that user
    from the PC itself. Open **MySQL Command Line Client** (or MySQL
    Workbench), log in as root, and run the lines below (choose your own
    password), then put `appuser` / that password into `.env`:

    ```sql
    CREATE USER 'appuser'@'%' IDENTIFIED BY 'choose-a-password';
    GRANT ALL PRIVILEGES ON *.* TO 'appuser'@'%';
    FLUSH PRIVILEGES;
    ```

    Use the same `appuser` account on the login page too.
- **Phone can't open the page** — run `allow-network-access.bat`, check
  the phone is on the same Wi-Fi (not mobile data), and use this PC's IP
  address, not `localhost`. Still not working? The Wi-Fi router may have
  "client/AP isolation" turned on, which stops devices seeing each other.
- **Port 8080 is already used by another program** — change
  `APP_PORT=8080` in `.env` to another number such as `8090`, run
  `start.bat`, and open `http://localhost:8090` instead.
- **Moved the app to a new PC?** Saved connection passwords are encrypted
  with a key that lives in Docker on the old PC, so just log in again on the
  new PC and the connections are saved again.

### What the Docker files do (for developers)

- `Dockerfile` — PHP 8.3 + Apache image with `pdo_mysql`. It uses
  `docker/config/*.php` instead of the local `config/` folder, so all
  settings come from environment variables (`.env`).
- `docker/entrypoint.sh` — on start, generates a persistent
  `ENCRYPTION_KEY` (stored in the `app-data` volume) if none is set, then
  runs `database/migrate.php`, retrying for ~2.5 minutes while MySQL comes
  up.
- `docker-compose.yml` — maps `host.docker.internal` to the host PC and sets
  `LOCALHOST_ALIAS`, so `localhost`/`127.0.0.1` typed on the login page
  reaches the host's MySQL rather than the container itself.
- `start.bat` / `stop.bat` / `logs.bat` — double-click wrappers around
  `docker compose up -d --build`, `down`, and `logs`.
- `allow-network-access.bat` — self-elevates and adds an inbound Windows
  Firewall rule ("MySQL Web Client") for `APP_PORT`. Docker Desktop's own
  rule is often limited to the Public profile, so LAN access fails on
  Private networks without it.

Equivalent commands without the `.bat` files:

```bash
cp .env.example .env      # then edit it
docker compose up -d --build
docker compose logs -f
docker compose down
```

## Manual setup (without Docker)

### Requirements

- PHP 8+ with the `pdo_mysql` extension
- A MySQL server for the app's own storage (connections + query log)
- Access to whatever MySQL server(s) you want to query

### Steps

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
docker/         Docker-only config (reads settings from .env) and container start-up script
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

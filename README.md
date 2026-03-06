# Ninja Saga Private Server
A complete Ninja Saga private server framework including database, custom client, QOL management tools, Live PvP, live chat, clan/crew systems, and an expanded admin panel for managing game data without modifying code.

---

## Requirements

- [Laragon](https://laragon.org) (includes Apache, MySQL, PHP)
- PHP 8.4+
- Composer
- Node.js 18+ and npm
- Git

---

## Setup

### 1. Install Laragon

Download and install [Laragon](https://laragon.org/download). The default install path is `C:\laragon`.

Start Laragon and make sure Apache and MySQL are both running.

---

### 2. Place the Server Files

Copy the `Database/Laragon/ninjasage` folder into Laragon's web root:

```
C:\laragon\www\ninjasage\
```

Your folder structure should look like:

```
C:\laragon\www\ninjasage\
  app\
  config\
  database\
  public\
  chat-server\
  pvp-server\
  ...
```

---

### 3. Configure Virtual Hosts

Copy the config files from `Database/apache2 sites/` into Laragon's Apache vhosts folder:

```
C:\laragon\etc\apache2\sites-enabled\
```

Files to copy:
- `00-default.conf`
- `auto.ninjasage.test.conf`
- `clan.ninjasage.id.conf`
- `crew.ninjasage.id.conf`

#### Copy the SSL certificates

The `clan` and `crew` vhosts use per-domain SSL certificates included in the repo. Copy all four files from `Database/etc/ssl/` into Laragon's SSL folder:

```
C:\laragon\etc\ssl\
```

Files to copy:
- `clan.ninjasage.id.pem`
- `clan.ninjasage.id-key.pem`
- `crew.ninjasage.id.pem`
- `crew.ninjasage.id-key.pem`

Then enable SSL: in the Laragon window, right-click the **Apache** entry and select **SSL → Enable**. Laragon will restart Apache automatically.

#### Troubleshooting: Apache fails to start after copying vhost configs

**Error: `Cannot access directory .../ninjasage/logs/`**

Apache requires the log directory to exist before it will start. Create it manually:

```
C:\laragon\www\ninjasage\logs\
```

---

### 4. Update the Windows Hosts File

Open `C:\Windows\System32\drivers\etc\hosts` as **Administrator** and add these lines:

```
127.0.0.1 ninjasage.test       #laragon magic!
127.0.0.1 clan.ninjasage.id
127.0.0.1 crew.ninjasage.id
```

> Without these entries, the virtual host domains will not resolve on your machine.

The main site will be available at: `https://ninjasage.test`

---

### 5. Configure the Laravel Environment

Inside `C:\laragon\www\ninjasage\`, copy the example env file and edit it:

```bash
cp .env.example .env
```

Open `.env` and set your database credentials:

```env
APP_NAME=NinjaSage
APP_URL=https://ninjasage.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ninjasage
DB_USERNAME=root
DB_PASSWORD=

CHAT_ADMIN_SECRET=your_secret_here
```

> By default Laragon's MySQL uses `root` with no password.

> **PHP version:** Make sure Laragon is using PHP 8.4 or later. You can switch PHP versions from the Laragon tray menu.

---

### 6. Install PHP Dependencies

In the Laragon window, click **Start All** to start Apache and MySQL before continuing.

Open a terminal in `C:\laragon\www\ninjasage\` and run:

```bash
composer install
php artisan key:generate
```

#### Troubleshooting: composer lock file error

If you see this error:

```
Your lock file does not contain a compatible set of packages. Please run composer update.
```

A required PHP extension is likely disabled. The most common fix is enabling the `zip` extension:

1. Open your active `php.ini`. It will be something like:
   ```
   C:\laragon\bin\php\php-8.4.x-Win32-vs17-x64\php.ini
   ```
2. Find the line:
   ```
   ;extension=zip
   ```
3. Remove the semicolon so it reads:
   ```
   extension=zip
   ```
4. Save the file and restart Laragon (or reload PHP from the tray menu).
5. Run `composer install` again.

---

### 7. Create the Database

Create the database using the Laragon terminal:

```bash
mysql -u root -e "CREATE DATABASE ninjasage;"
```

Then run the migrations and seed the initial data:

```bash
php artisan migrate
php artisan db:seed
```

Once complete, verify the admin panel is working by visiting:

```
https://ninjasage.test/admin/login
```

Default credentials:

| Field    | Value            |
|----------|------------------|
| Email    | admin@admin.test |
| Password | admin            |

---

### 8. Set Up the Chat Server

Open a new terminal and navigate into the chat server folder:

```bash
cd C:\laragon\www\ninjasage\chat-server
npm install
```

Copy the example env file and edit it:

```bash
cp .env.example .env
```

```env
PORT=3002
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ninjasage
DB_USER=root
DB_PASS=
CHAT_ADMIN_SECRET=your_secret_here
```

> `CHAT_ADMIN_SECRET` must match the value you set in the Laravel `.env`.

Start the chat server:

```bash
npm start
```

You should see:

```
> chat-server@1.0.0 start
> node index.js

[Chat] Flash socket policy server listening on port 843
[MessageStore] chat_messages table ready
[Chat] Socket.IO server listening on port 3002
[Chat] Namespaces: /global-chat, /clan-chat
```

---

### 9. Set Up the PvP Server

Open another new terminal and navigate into the PvP server folder:

```bash
cd C:\laragon\www\ninjasage\pvp-server
npm install
```

Copy the example env file and edit it:

```bash
cp .env.example .env
```

```env
PORT=3000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ninjasage
DB_USER=root
DB_PASS=
```

Start the PvP server:

```bash
npm start
```

You should see:

```
> pvp-server@1.0.0 start
> node index.js

[SkillData] loaded 1158 skills, 833 skill-effect entries
[PVP] Socket.IO server listening on port 3000
[PVP] Namespace: /pvp
[PVP] Turn duration: 30s
[PVP] Max rounds: 50
```

> The chat and PvP servers are optional — the main server works without them, but those features will not be functional.

---

## Running All Servers

You need three things running at the same time:

| Service        | How to start                      | Port |
|----------------|-----------------------------------|------|
| Apache + MySQL | Laragon tray icon → Start All     | 80   |
| Chat Server    | `npm start` inside `chat-server\` | 3002 |
| PvP Server     | `npm start` inside `pvp-server\`  | 3000 |

---

## Launching the Game

Run `NSCUSTOM.exe` from `Custom Client/NS Custom Client V1/`.

Default game login:

| Field    | Value |
|----------|-------|
| Username | Admin |
| Password | Admin |

To manage accounts, items, skills, and other game data, use the admin panel:

```
https://ninjasage.test/admin
```

| Field    | Value            |
|----------|------------------|
| Email    | admin@admin.test |
| Password | admin            |

---

## Custom Client Tools

To rebuild or patch the client, use the Python tools in the `QoL Tools/` folder:

- `CustomClientBuilder.py` — builds the client package
- `ninjasage_patcher.py` — patches the SWF to point to your server
- `gamedata_converter.py` — converts game data files

---

## Useful Artisan Commands

Open a terminal in the server folder and run `php artisan tinker` to interact with the database directly.

See `Database/Laragon/ninjasage/Documentation/Commands.txt` for example commands to add items, skills, pets, and more to characters.

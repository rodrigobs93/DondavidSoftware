# Mi Negocio POS — Windows Installer

One-click installation + one-click run for Mi Negocio POS on Windows 10/11 x64.
Each PC is standalone (own PHP, own Postgres, own DB — no cross-PC sync).

---

## What gets installed

| Component | Bundled? | Location |
|-----------|----------|----------|
| PHP 8.2 (non-thread-safe, CLI) | yes | `C:\MiPOS\php\` |
| PostgreSQL 16 (portable) | yes | `C:\MiPOS\pgsql\` — runs as Windows service `MiPOSPostgres` |
| Laravel app (vendor\ pre-installed) | yes | `C:\MiPOS\app\` |
| Composer (for future in-place upgrades) | yes | `C:\MiPOS\composer.phar` |
| Launcher scripts | yes | `C:\MiPOS\scripts\` |

**Not** bundled: the thermal printer driver (XP-80C or whichever model is used
on that PC). Install the driver manually first, then run the installer and
type the printer queue name into the wizard.

---

## Building the installer (dev machine, one-time per release)

### Prerequisites
- Windows 10/11 x64
- [Inno Setup 6](https://jrsoftware.org/isinfo.php) installed (default path
  `C:\Program Files (x86)\Inno Setup 6\`)
- Internet access (for downloading PHP / Postgres / Composer on first build)
- The repo cloned; no changes needed in `app\` beyond your usual commits

### Build
```powershell
cd c:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware\installer
.\build-installer.ps1
```

This will:
1. Download portable PHP 8.2 and Postgres 16 zips into `installer\payload\`
   (only once — subsequent builds reuse them).
2. Copy the `app\` folder into `payload\app\` and run
   `composer install --no-dev --optimize-autoloader`.
3. Invoke Inno Setup Compiler to produce
   `installer\output\MiPOSSetup-<version>.exe` (~250–350 MB).

Skip flags:
- `-SkipPhpPostgres` — reuse existing payload if you know it's up-to-date.
- `-SkipApp` — reuse existing payload\app (e.g. when iterating on scripts).

### Release an update
Increase `#define AppVersion` in `MiPOS.iss`, commit, then rebuild and copy
the new `.exe` to each PC and re-run it. The installer is idempotent: existing
`.env`, DB, and admin user are preserved; new migrations apply automatically.

---

## Installing on a cashier PC (per-machine, once)

1. Install the thermal printer driver manually; verify it prints a Windows test
   page. Note the exact queue name (Control Panel > Devices and Printers).
2. Copy `MiPOSSetup-<version>.exe` to the target PC.
3. Right-click → Run as administrator (or double-click and approve UAC).
4. Follow the wizard:
   - **Install location**: keep default `C:\MiPOS`.
   - **HTTP port**: keep `8000` unless already in use.
   - **Printer queue name**: the exact queue name from step 1 (e.g. `XP-80C`).
   - **Admin email + password**: credentials for the first admin user.
   - **Auto-start at boot**: check if this is a cashier PC.
5. Wait for "Setup completed successfully". A **Mi Negocio POS** icon appears on
   the desktop.

Total unattended install time: ~2 minutes (most of which is extracting
Postgres + composer autoload dump).

---

## Daily operation

Double-click **Mi Negocio POS** on the desktop. The launcher:
1. Acquires an exclusive lock (no double-boot even if you click 10x fast).
2. Starts Postgres if stopped (usually already running — it's a service).
3. Starts `php artisan serve` on the configured port, if not already running.
4. Starts the print worker (`php artisan app:print-worker`), if not already running.
5. Waits for HTTP 200, then opens the browser once.

Subsequent clicks just open/focus the browser — nothing is spawned twice.

### Status / stop / restart
Scripts in `C:\MiPOS\scripts\`:
- `status.ps1` — prints Postgres / Laravel / Worker running state + PIDs.
- `stop.ps1`   — stops Laravel + Worker (leaves Postgres running).
- `start.ps1`  — what the desktop icon runs.

Right-click any of them → Run with PowerShell (or make a Start Menu shortcut).

### Logs
- `C:\MiPOS\logs\launcher.log` — one line per run of `start.ps1`.
- `C:\MiPOS\logs\laravel-YYYY-MM-DD.log` — Laravel server stdout/stderr.
- `C:\MiPOS\logs\worker-YYYY-MM-DD.log`  — print worker stdout/stderr.
- `C:\MiPOS\logs\install.log` — full transcript of the install script.

---

## Uninstall

Control Panel > Programs → select **Mi Negocio POS** → Uninstall. This:
- Stops Laravel + Worker (`stop.ps1`)
- Stops and unregisters the `MiPOSPostgres` service
- Removes the firewall rule
- Removes the desktop + startup shortcuts
- Removes `C:\MiPOS\` (including `pgsql\data\` — **your data is deleted**;
  back it up via the in-app Admin > Backups feature first).

---

## Troubleshooting

| Symptom | First check |
|---------|-------------|
| Icon click does nothing | Open `C:\MiPOS\logs\launcher.log`; most recent line shows where it bailed |
| Browser opens but "can't reach" | Laravel failed to start — see `logs\laravel-<today>.log` |
| Browser says DB error | Postgres not running — `Get-Service MiPOSPostgres` |
| Printing silently fails | Queue name in Admin > Config doesn't match Windows spooler; correct it in-app, no reinstall needed |
| Two PHP processes after clicking icon twice | File a bug — should be impossible. Include `launcher.log` |

---

## Future work (out of scope for v1)

- macOS / Linux installers
- Auto-update on launch via GitHub releases
- Optional cross-PC sync (different LANs today — would require a small
  sync service or manual DB export/import)
- Bundling common thermal printer drivers

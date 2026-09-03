# STI CCTV Portal

A secure internal web portal for accessing Hikvision NVR/CCTV camera feeds.
Users see a tree of NVRs and cameras; clicking one opens that device's own
Hikvision web interface in a new tab. Availability is checked continuously
and pushed to the browser in real time over WebSockets.

```
NVR 1
 ├─ Computer Lab 101 — 192.168.1.11        ● online
 └─ Admissions and Lobby View — 192.168.1.12   ● offline

NVR 2
 ├─ Room 502 — 192.168.1.21                ● online
 └─ Photography Room — 192.168.1.22        ● online
```

Stack: **PHP 8.1+ / MySQL / vanilla HTML+CSS+JS**, with a small
**Ratchet (PHP) WebSocket server** for the real-time status feed. No
Node/npm/React required.

---

## 1. How it works

| Concern | How it's handled |
|---|---|
| **Access control** | Session-based login. Three roles (below). Viewers only ever see NVRs/cameras that are flagged "public" or explicitly granted to them. |
| **Admin CRUD** | `/admin/nvrs.php` and `/admin/cameras.php` — add, edit, delete NVRs and cameras, including IP, port, optional URL override, and optional device credentials (encrypted at rest). |
| **Per-user access** | `/admin/permissions.php` — grant/revoke a viewer's access at the NVR level (covers all its cameras) or at the individual camera level. |
| **Availability check** | `websocket/server.php` opens a raw TCP connection to each device's HTTP port every 30s (configurable). No Hikvision SDK or credentials required — it just confirms something is listening. Status flips to `online`/`offline` and is pushed to every connected browser instantly. |
| **Redirect on click** | `redirect.php?type=camera&id=5` re-checks the current user's permission server-side, then does an HTTP redirect to the device's own web UI (`http://<ip>:<port>` or a configured override URL), opened in a new tab. |
| **Audit trail** | Every login, logout, device view, access grant/revoke, and CRUD action is written to `audit_log`. |

### User roles

- **super_admin** — full control: manage NVRs/cameras, manage *all* user
  accounts including other admins, manage permissions, delete accounts.
- **admin** — manage NVRs/cameras (add/edit/delete) and manage access
  grants; can create/edit **viewer** accounts only (cannot create or edit
  admins/super_admins, cannot delete accounts).
- **viewer** — the end user (e.g. a guard, dept staff, org officer). Can
  only open NVRs/cameras that are marked public or that an admin has
  explicitly granted to their account.

---

## 2. Requirements

- PHP 8.1+ with `pdo_mysql`, `openssl`, `mbstring` extensions
- MySQL 8.0+ or MariaDB 10.5+
- Composer
- A webserver (Apache/Nginx) pointed at the `public/` folder — **not** the
  project root
- Network line-of-sight from the server to your NVRs/cameras (same VLAN or
  routed access to `192.168.1.x`)

## 3. Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Create the database and seed data
mysql -u root -p < database/schema.sql

# 3. Configure environment
cp .env.example .env
# then edit .env:
#   - DB_HOST / DB_NAME / DB_USER / DB_PASS
#   - APP_ENCRYPTION_KEY  -> generate with: openssl rand -base64 32
#   - WS_PUBLIC_URL       -> the ws:// (or wss://) address browsers will connect to

# 4. Point your webserver's document root at public/
#    Apache example vhost:
#      DocumentRoot /path/to/sti-cctv-portal/public
#      <Directory /path/to/sti-cctv-portal/public>
#          AllowOverride All
#          Require all granted
#      </Directory>

# 5. Start the WebSocket status server (keep it running with systemd/supervisor)
php websocket/server.php
```

### First login

A seed super admin is created by `schema.sql`:

```
username: superadmin
password: ChangeMe!123
```

**Change this password immediately** after first login (there's no
self-service "change my password" screen yet — a super admin can reset it
from Admin → Users, or update it directly in the database with
`password_hash()`).

### Adding your real NVRs/cameras

Log in as `superadmin` → **Admin panel** → **NVRs** → add each NVR with its
real IP/port. Then go to **Cameras** to add each camera/channel under its
NVR, matching your layout, e.g.:

- NVR 1 → Computer Lab 101 (192.168.1.11), Admissions and Lobby View (192.168.1.12)
- NVR 2 → Room 502 (192.168.1.21), Photography Room (192.168.1.22)

Toggle **"Make visible to all viewers"** for anything that should be public,
or leave it unchecked and grant access per-user under **Access & permissions**.

---

## 4. Security notes (read before deploying)

This app handles access to live camera feeds — take these seriously:

1. **Serve over HTTPS.** Put the portal behind TLS (and `wss://` for the
   websocket) — otherwise session cookies and any device credentials
   entered in the admin forms can be intercepted on the network.
2. **Don't expose NVRs directly to the internet.** This portal is meant to
   run on your internal network/VPN. If remote access is needed, put the
   whole thing behind a VPN or a properly configured reverse proxy — don't
   port-forward Hikvision devices directly.
3. **Change the seeded password and the `APP_ENCRYPTION_KEY`** before going
   live. Never commit `.env` to version control.
4. **Least privilege by default.** New NVRs/cameras are created as
   *not public* — you opt them in, rather than opting out.
5. **Device credentials are optional.** If you don't need the portal to
   log into the device on the user's behalf, leave the admin
   username/password fields blank — the redirect just opens the device's
   own login page and the device handles its own auth.
6. **Keep Hikvision firmware patched.** Hikvision devices have had serious
   CVEs in the past; this portal doesn't change that exposure, it just
   controls who can reach the device's own web UI.
7. **Rate-limit/lock out logins** if you expose this beyond a trusted LAN —
   this build does not include brute-force lockout; consider adding
   fail2ban at the webserver level or a login-attempt counter in `users`.

---

## 5. Project structure

```
sti-cctv-portal/
├── composer.json
├── .env.example
├── database/
│   └── schema.sql                # tables + seed NVRs/cameras/superadmin
├── config/
│   ├── Config.php                # .env loader
│   └── Database.php              # PDO singleton
├── src/
│   ├── Auth.php                  # sessions, CSRF, RBAC helpers
│   ├── CctvRepository.php        # permission-filtered NVR/camera tree
│   ├── CameraStatusService.php   # TCP availability checker
│   └── Crypto.php                # AES-256 encryption for device creds
├── websocket/
│   └── server.php                # Ratchet WS server + periodic status poll
└── public/                       # <-- point your webserver here
    ├── index.php, login.php, logout.php, dashboard.php, redirect.php
    ├── admin/
    │   ├── nvrs.php, cameras.php, users.php, permissions.php
    │   └── _header.php, _footer.php
    └── assets/
        ├── css/style.css         # STI blue/yellow/white theme
        └── js/ws-client.js       # live status updates, no framework
```

---

## 6. Extending this

- **Live video, not just the login page:** the redirect currently opens the
  device's own web UI. To embed an actual live stream in the portal itself,
  you'd add a media server (e.g. `go2rtc` or MediaMTX) that pulls each
  camera's RTSP stream and re-serves it as HLS/WebRTC for the browser —
  a meaningfully bigger project than this scaffold, happy to help if you
  want to go that route.
- **Stronger availability check:** swap the TCP-connect check in
  `CameraStatusService` for a Hikvision ISAPI call
  (`GET /ISAPI/System/status`) using the stored device credentials, for a
  true "is the device actually functioning" check instead of "is a port open".
- **Password self-service & MFA** for admin/super_admin accounts.

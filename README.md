# Azizo Butchery & Grill — Website + Backend

Full source for the Azizo Butchery and Grill website: a static, responsive
multi-page frontend (home, fresh market, ready-to-eat, meal planner, outdoor
events, recipes, about, reviews, FAQ, contact) backed by an admin-managed
product catalog and order log. The frontend adapts across mobile, tablet and
desktop breakpoints and works as an installable PWA.

## Repo structure

```
site/           Static frontend — HTML/CSS/JS, deploy as-is to any web host
backend/        Node.js + Express + SQLite API (recommended)
backend-php/    PHP + MySQL alternative API, for hosts without Node support
```

Pick **one** backend, not both — `site/app.js`'s `CONFIG.API_BASE_URL` just
needs to point at whichever one you deploy. Without any backend running, the
site still works fully off the bundled `site/products.json` snapshot and
sends orders over WhatsApp; the backend adds a live-editable catalog, an
admin orders dashboard, and optional customer accounts.

## Quick start (Node backend, local)

```bash
cd backend
npm install
cp .env.example .env    # then edit ADMIN_PASSWORD and JWT_SECRET
npm start                # or: node server.js
```

Then open `site/index.html` with `site/app.js`'s `CONFIG.API_BASE_URL` set to
`http://localhost:3000` (or serve the whole repo with any static file server
so relative paths resolve). Admin dashboard: `site/admin.html`.

## Quick start (PHP backend, alternative)

See `backend-php/config.php` and `backend-php/schema.sql` — point a MySQL
database at the schema, edit the config constants (or set the matching
environment variables), and point your web server's document root at
`backend-php/`.

## Deploying for real

- `backend/README.md` — Node backend setup, environment variables, and how
  it connects to the frontend.
- Before go-live: set real `ADMIN_PASSWORD`/`JWT_SECRET` values, add real
  M-Pesa Daraja credentials and a Google Maps API key if you want live
  delivery-distance pricing, replace placeholder product photos via the
  admin dashboard, and verify branch GPS coordinates.

## Branches, tracking & free map services

- **Branches (admin ▸ Branches tab):** tick *Show on website* to publish a branch; *Edit* changes its address, map pin, hours and its own call-me phone number. The WhatsApp ordering number is the same for every branch (`CONFIG.WA_NUMBER` in `site/app.js`). Ten extra Nairobi middle/high-end branches ship **unlisted** with approximate pins — check a pin before publishing. Without a backend the site reads `site/branches.json` instead.
- **Order tracking:** after checkout the customer gets an order number; *Account ▸ Track an order* (or any page with `?track=123`) shows its status from the number alone (`GET /api/orders/track`, status-only reply, rate-limited). Admin statuses: received → preparing → ready / out for delivery → completed.
- **Free map services:** delivery fees use road distance from OSRM (straight-line estimate if unreachable); address search uses Photon with OpenStreetMap Nominatim as backup. These are shared free services — fine for light use; a Google key (optional) is used first when set.
- **Catalog top-up:** on start-up an existing database gets the new menu items once (sausages, stew packs, fish baskets, broth bundle) without touching your edits. Prices are placeholders — edit them in admin.
- **PHP backend:** has branches + tracking, but its products API is older than the Node one (no groceries).

## Responsive design

Every page ships with mobile/tablet/desktop breakpoints (verified at
375px, 768px and 1440px widths with zero horizontal overflow, including the
admin orders dashboard) and a mobile-first checkout flow. No further work is
needed here before launch.

# Changelog

## v1.9 — 2026-10-21
- **Install the app**: on first visit, phones and PCs (Chrome, Edge, Android; Safari on iPhone shows "Share → Add to Home Screen" steps) get an "Install the Azizo app" bar. "Not now" hides it for 7 days; it never shows again once installed or when already running as an app. A footer "Install app" link brings it back.
- **Automatic updates**: the app checks for a new version when opened, when you return to it, and every 30 minutes. When one is found, an "A new version is ready — Update now" bar appears; tapping it reloads into the new version. Footer shows "App v1.9 · Check for updates" for a manual check.
- Manifest gains an app id, separate maskable icons and home-screen shortcuts (Order meat, Meat Budget Planner, Areas We Serve). New `pwa.js` on all customer pages.
- Needs HTTPS (or localhost) to install; staff, rider and admin screens are unchanged.
- **For future releases**: bump `CACHE_NAME` in `sw.js` and the version in `pwa.js` whenever you change site files. That is what tells installed apps an update exists.

## v1.8 — 2026-10-21
- **Repeat-customer layer**: the site remembers name, phone, area and address on the device and pre-fills checkout; a "Your usual" shelf of one-tap chips (shop and cart); optional standing orders (Weekly / Bi-weekly / Monthly) that appear in the staff Standing tab with a DUE badge and a one-tap "Create this order".
- **Customer status messages**: staff and rider screens get a one-tap "Tell customer" WhatsApp button prefilled for the current status. (Fully automatic messages need the WhatsApp Business API, not included.)
- **Reviews with photos**: completed orders can be reviewed from the tracking panel (order number + phone, stars, comment, optional photo). Admin > Reviews to approve, hide, reply or delete; approved reviews show on `reviews.html` with aggregateRating markup.
- **Rider screen** (`rider.html`): deliveries grouped by branch and corridor, cash to collect, Out for delivery / Delivered buttons.
- **Stock by branch**: staff mark items out per branch; checkout warns and offers a one-tap switch to a branch that has everything.
- New `DOCUMENTATION.md` with the full handbook and API table; README updated. Both backends updated (PHP not run on real MySQL).

## v1.7 — 2026-10-20
- **Areas We Serve** (`areas.html`): every region of Nairobi split into zones (138 zones in 15 regions, e.g. Umoja 1/2/Innercore, Buruburu Phase 1-5, Kilimani, Kileleshwa, Lavington, Westlands, Karen, Kasarani/Roysambu...). Customers search or pick their estate and see the serving branch, distance and delivery fee. Added to the footer, mobile menu and sitemap.
- **Admin > Areas**: tick which zones are served (single zone, whole region, or "serve all within X km of a live branch"), add/edit/delete zones with a Google Maps check of each pin, and edit the delivery pricing. Zones within about 12 km of your four live branches start ticked; the rest start off.
- **Zone-banded delivery pricing**: road distance from the nearest live branch is rounded up to a band (default 2 km), fee = base + rate per km x band, capped (defaults KSh 100 + 25/km, cap 450). Replaces the old exact-per-km formula, so areas at a similar distance cost the same. Fee estimate appears as soon as an area is chosen.
- **Smart nearest branch** at checkout: pick your area or tap "Use my location" and the nearest live branch is selected for pickup and for dispatch. Choosing a branch that is 2.5 km or more farther shows a one-tap "switch to the nearer branch" suggestion.
- **Branch closed?** Hide it in Branches and every quote, suggestion and zone re-routes to the next nearest live branch.
- Both backends (Node and PHP) have the areas API; existing databases add the tables and seed themselves.

## v1.6 — 2026-10-20
- **Floating Call and WhatsApp buttons** on every page, visible from the first moment and through all scrolling (bottom-left; they hide while the mobile menu is open). The existing Buy Now button is unchanged. WhatsApp moved out of the small social rail into these larger buttons; the rail now shows only if social links are configured.

## v1.5 — 2026-10-20
- **Preferred time** at checkout for both pickup and delivery (as soon as possible, today / tomorrow morning, afternoon, evening, or another day to confirm on WhatsApp). Replaces the old pickup-only clock field.
- **Instructions for the butcher** box (e.g. "mince the beef coarse, leave the fat on the ribs"). Appears in the WhatsApp order, the PDF receipt, and highlighted on the staff order card and printed ticket.
- Orders store the note (Node and PHP backends; existing databases update themselves).

## v1.4 — 2026-10-19
- **Meat Budget Planner** (`budget.html`): customers build a weekly, monthly or annual meat budget. Enter a name, household size and optional budget; pick meats, tick the days of the week for each, adjust the portion. Live calculator, cost per week/month/year, per-person cost, budget bar, money-saving swap tips and variety tips, a nutritional-benefits column for every meat, and a day-by-day timetable.
- **Download as PDF** (with a print fallback), share on WhatsApp, or quietly add one week's meat to the cart. Plan is remembered on the device.
- Entry points kept away from the buying flow: link on Fresh Market, banner on Meal Plan, footer and mobile menu. Nothing in the cart or checkout.

## v1.3 — 2026-10-19
- **Cost backfill** (Reports → Accounts → "Fill in costs on older orders"): copies each product's current cost price onto past order lines that have no cost yet, so older sales get a margin. Preview first, then apply; for the selected dates or all past orders. Lines that already have a cost are never changed.
- **Expenses can now be edited** (Edit button beside each entry), not only deleted and re-added.

## v1.2 — 2026-10-19
- **Reports module (admin → Reports tab)** for the owner, sales, business-development and accounting hats: Overview (revenue, orders, average order, profit, customers, cancellations, time to complete, plain-language "what to act on", sales trend, busiest hours, best weekdays), Products & Sales (best sellers with margin and A/B/C class, categories, low margin, sold out, no-sales items), Branches & Delivery, Customers (new vs repeat, top customers, win-back list with WhatsApp nudge, promo codes), Payments & Cash (collected vs outstanding, M-Pesa receipt reconciliation, cash to bank) and Accounts (profit & loss, expenses, optional tax estimate).
- Date presets and custom range, branch filter, comparison with the previous equal period, sortable tables with CSV download, Print / Save as PDF.
- **Accountant pack (.xlsx)**: P&L, daily sales, products, branches, payments, expenses and the full sales ledger (PHP backend: sales ledger only).
- **Expenses**: record rent, salaries, utilities, transport, stock purchases etc. in Reports → Accounts.
- **Cost price per product** (private, admin only) in the product form and Excel import/export; each sold item keeps the cost that applied that day so margins stay accurate. Orders now keep a status history for "time to complete".
- Both backends (Node and PHP) produce identical report numbers.

## v1.1 — 2026-10-18
- **Product cards redesigned marketplace-style:** photos fill the card edge to edge (4:3), equal-height rows, two-line titles, bold price, full-width Add button, two cards per row on phones. Fixes the empty strip beside uploaded photos.
- Admin photo form now shows a tip on the best photo shape (landscape 4:3, e.g. 1200 × 900).

## v1.0 — 2026-10-17
- **Staff screen** (`staff.html`): live order queue with full tickets, one-tap status steps, call / WhatsApp / map buttons, print ticket, Today totals, sold-out switch, promo codes, branch directory. Own `STAFF_PASSWORD`.
- **Order tracking** by order number (`?track=123`), tracking link in the WhatsApp order message, "Track My Order" link in every footer.
- **Branches** managed in admin (show/hide, edit, per-branch phone); WhatsApp number stays shared. 10 new Nairobi branches added, hidden until published.
- **Free maps**: OSRM road distance, Photon + Nominatim address search (Google optional).
- **Products**: new items (sausages, stew packs, fish baskets, broth bundle), Free-Range badge fix, new short descriptions, "Read more" on cards.

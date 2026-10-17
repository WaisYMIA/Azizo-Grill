const Database = require('better-sqlite3');
const path = require('path');
const DB_PATH = process.env.DB_PATH || path.join(__dirname, 'azizo.db');
const db = new Database(DB_PATH);
db.pragma('journal_mode = WAL');
db.exec(`
  CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL, created_at TEXT DEFAULT (datetime('now'))
  );
  CREATE TABLE IF NOT EXISTS products (
    id TEXT PRIMARY KEY, category_group TEXT NOT NULL, cat TEXT NOT NULL, kind TEXT,
    name TEXT NOT NULL, desc TEXT, price INTEGER NOT NULL, unit TEXT NOT NULL,
    img TEXT, badges TEXT, origin TEXT, updated_at TEXT DEFAULT (datetime('now'))
  );
  CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER, customer_name TEXT,
    customer_phone TEXT NOT NULL, fulfilment TEXT NOT NULL, branch TEXT, pickup_time TEXT,
    delivery_address TEXT, delivery_lat REAL, delivery_lng REAL, delivery_fee INTEGER DEFAULT 0,
    payment_method TEXT NOT NULL, mpesa_receipt TEXT, promo_code TEXT,
    items_subtotal INTEGER NOT NULL, total INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'received', created_at TEXT DEFAULT (datetime('now'))
  );
  CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id TEXT, name TEXT NOT NULL, qty INTEGER NOT NULL, unit TEXT,
    unit_price INTEGER NOT NULL, line_total INTEGER NOT NULL
  );
  CREATE TABLE IF NOT EXISTS branches (
    id TEXT PRIMARY KEY, name TEXT NOT NULL, area TEXT, address TEXT, query TEXT,
    lat REAL, lng REAL, phone TEXT DEFAULT '', hours TEXT DEFAULT 'Open 24 hours',
    is_main INTEGER NOT NULL DEFAULT 0, listed INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0, updated_at TEXT DEFAULT (datetime('now'))
  );
  CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id),
    checkout_request_id TEXT, phone TEXT, amount INTEGER, status TEXT DEFAULT 'pending',
    mpesa_receipt TEXT, raw_callback TEXT, created_at TEXT DEFAULT (datetime('now'))
  );
`);

// First-run seed: the live menu as of this build, so a fresh deploy (new database,
// no products.json, no GitHub sync) still serves a real, usable catalog immediately
// instead of an empty one. Only runs when the table is empty — never overwrites
// products you've since added, edited, or deleted through the admin tool.
// Photos are simple original placeholder icons (site/images/*.svg) built for this
// project, bundled as real local files — no internet connection needed to see them.
// Swap them for real product photos any time via admin.html's uploader.
// The built-in menu. Photos are simple original placeholder icons (site/images/*.svg) built
// for this project, bundled as real local files — no internet connection needed to see them.
// Swap them for real product photos any time via admin.html's uploader.
// Row: [id, section, category, kind, name, description, price, unit, image, badges?]
const seedRows = [
  ['f1','fresh','Beef','red','Beef Rump','Lean, tender cut ideal for grilling or roasting. Cut to your preferred thickness.',650,'kg','beef-raw.svg'],
  ['f2','fresh','Beef','red','Beef Ribeye','Well-marbled steak cut, perfect for the grill.',850,'kg','beef-raw.svg'],
  ['f3','fresh','Beef','red','Beef Mince','Freshly ground beef, great for stews and burgers.',600,'kg','beef-raw.svg'],
  ['f4','fresh','Pork','red','Pork Chops','Bone-in chops, trimmed and ready to marinate.',700,'kg','pork-raw.svg'],
  ['f5','fresh','Pork','red','Pork Ribs','Full rack, ideal for slow grilling.',750,'kg','pork-raw.svg'],
  ['f6','fresh','Chicken','white','Broiler Chicken (Whole)','Standard commercial broiler, farm-fresh, cleaned and ready to cook — the everyday choice.',550,'bird','chicken-raw.svg'],
  ['f7','fresh','Chicken','white','Chicken Breast','Skinless, boneless fillets.',520,'kg','chicken-raw.svg'],
  ['f8','fresh','Fish','white','Tilapia (Whole)','Fresh whole tilapia, scaled and gutted on request.',450,'kg','fish-raw.svg'],
  ['f9','fresh','Fish','white','Nile Perch Fillet','Boneless fillet, ready for the pan or grill.',700,'kg','fish-raw.svg'],
  ['r1','ready','Grilled',null,'Nyama Choma (Beef)','Charcoal-grilled beef, served with kachumbari on request.',700,'kg','grilled.svg'],
  ['r2','ready','Grilled',null,'Grilled Chicken','Half or whole, marinated and grilled to order.',600,'half','grilled.svg'],
  ['r3','ready','Fried',null,'Fried Chicken','Crispy fried chicken pieces, made fresh to order.',550,'plate','fried.svg'],
  ['r4','ready','Fried',null,'Fried Fish','Deep-fried tilapia, whole or filleted.',500,'plate','fried.svg'],
  ['r5','ready','Shawarma',null,'Beef Shawarma','Wrapped shawarma with beef, salad and house sauce.',350,'wrap','shawarma.svg'],
  ['r6','ready','Shawarma',null,'Chicken Shawarma','Wrapped shawarma with chicken, salad and house sauce.',320,'wrap','shawarma.svg'],
  ['f10','fresh','Beef','red','Sirloin Steak','Tender, well-trimmed steak cut, great seared or grilled.',900,'kg','beef-raw.svg'],
  ['f11','fresh','Beef','red','T-Bone Steak','Classic bone-in steak with fillet and sirloin on either side.',950,'kg','beef-raw.svg'],
  ['f12','fresh','Beef','red','Topside Steak','Lean, economical cut — ideal for roasting or slow cooking.',750,'kg','beef-raw.svg'],
  ['f13','fresh','Beef','red','Beef Short Ribs','Flanken-style ribs, great for braising or the grill.',700,'kg','beef-raw.svg'],
  ['f14','fresh','Beef','red','Oxtail','Rich, gelatinous cut — a classic for slow-cooked stew.',800,'kg','beef-raw.svg'],
  ['f15','fresh','Beef','red','Beef Liver','Fresh beef liver, trimmed and ready to cook.',550,'kg','beef-raw.svg'],
  ['f16','fresh','Beef','red','Beef Soup Bones','Marrow bones for a rich, home-style bone broth.',250,'kg','beef-raw.svg'],
  ['f17','fresh','Chicken','white','Chicken Thighs','Juicy bone-in thighs, skin on.',600,'kg','chicken-raw.svg'],
  ['f18','fresh','Chicken','white','Chicken Drumsticks','Whole drumsticks, farm fresh.',550,'kg','chicken-raw.svg'],
  ['f19','fresh','Chicken','white','Chicken Wings','Great for grilling or deep-frying.',500,'kg','chicken-raw.svg'],
  ['f20','fresh','Chicken','white','Chicken Liver','Fresh chicken liver, cleaned and ready.',350,'kg','chicken-raw.svg'],
  ['f21','fresh','Chicken','white','Gizzard','Cleaned chicken gizzard.',400,'kg','chicken-raw.svg'],
  ['f22','fresh','Fish','white','Prawns','Fresh prawns, cleaned and deveined.',1800,'kg','fish-raw.svg'],
  ['f23','fresh','Fish','white','Salmon Fillet','Boneless imported salmon fillet.',3500,'kg','fish-raw.svg'],
  ['f24','fresh','Fish','white','Omena (Dagaa)','Sun-dried silver cyprinid, a Kenyan favourite.',400,'kg','fish-raw.svg'],
  ['f25','fresh','Goat',null,'Goat Ribs','Ribs cut for nyama choma, bone-in.',800,'kg','goat-raw.svg'],
  ['f26','fresh','Goat',null,'Goat Meat (Mbuzi)','Cubed goat meat, ready for stew or grill.',750,'kg','goat-raw.svg'],
  ['f27','fresh','Goat',null,'Mutton/Lamb Chops','Tender chops, cut to order.',950,'kg','goat-raw.svg'],
  ['g1','groceries','Vegetables',null,'Tomatoes','Fresh tomatoes — kachumbari and stew base.',120,'kg','veggies.svg'],
  ['g2','groceries','Vegetables',null,'Onions','Red onions, essential for any meat dish.',100,'kg','veggies.svg'],
  ['g3','groceries','Vegetables',null,'Dhania (Coriander)','Fresh coriander, sold by the bunch.',30,'bunch','veggies.svg'],
  ['g4','groceries','Vegetables',null,'Pilipili (Chili)','Fresh green or red chili, for kachumbari or marinades.',50,'250g','veggies.svg'],
  ['g5','groceries','Vegetables',null,'Lemon','Fresh lemons — great on grilled fish or chicken.',15,'piece','veggies.svg'],
  ['g6','groceries','Vegetables',null,'Potatoes','Fresh potatoes — chips or a stew side.',90,'kg','veggies.svg'],
  ['g7','groceries','Vegetables',null,'Kachumbari Mix','Pre-chopped tomato, onion and coriander — ready to serve.',150,'pack','veggies.svg'],
  ['g8','groceries','Spices',null,'Garlic','Fresh garlic bulbs.',200,'250g','spices.svg'],
  ['g9','groceries','Spices',null,'Ginger','Fresh ginger root.',150,'250g','spices.svg'],
  ['g10','groceries','Spices',null,'Royco / Meat Masala','All-purpose seasoning for stews and grills.',80,'packet','spices.svg'],
  ['g11','groceries','Spices',null,'Cooking Oil','Vegetable cooking oil, 1 litre.',280,'litre','spices.svg'],
  ['g12','groceries','Spices',null,'Salt','Table salt, 1kg packet.',50,'packet','spices.svg'],
  ['f28','fresh','Chicken','white','Kienyeji Chicken (Whole)','Free-range, slow-grown kienyeji chicken — firmer texture and the deeper flavour Kenyan households and hotels pay a premium for.',1200,'bird','chicken-raw.svg',['Free-Range']],
  ['f29','fresh','Fish','white','Smoked Fish (Tilapia)','Whole tilapia smoked over firewood the traditional way — ready to flake straight into stew, ugali or managu.',600,'kg','fish-raw.svg'],
  ['f30','fresh','Fish','white','Omena (Fresh/Wet)','Undried fresh dagaa — for frying at home the same day, or straight into the pot.',250,'kg','fish-raw.svg'],
  ['r7','ready','Fried',null,'Fried Omena (Crispy Dagaa)','Crispy deep-fried dagaa, a Kenyan favourite bar snack and ugali side — served hot and salty.',300,'plate','fried.svg'],
  ['r8','ready','Grilled',null,'Grilled Kienyeji Chicken','Half or whole kienyeji bird, marinated and charcoal-grilled — firmer meat and richer flavour than broiler.',750,'half','grilled.svg',['Free-Range']],
  ['g13','groceries','Eggs',null,'Eggs (Tray of 30)','Standard commercial eggs, tray of 30 — for breakfast, baking or mayai pasua.',450,'tray','eggs.svg'],
  ['g14','groceries','Eggs',null,'Kienyeji Eggs (Tray of 30)','Free-range kienyeji eggs — smaller, richer yolk, the ones households ask for by name.',650,'tray','eggs.svg',['Free-Range']],
];

// Menu additions made after the first release. Prices here are sensible starting points,
// not final — set your real ones in admin.html.
const catalogV2Rows = [
  ["f31","fresh","Sausages","red","Beef Sausages","Juicy beef sausages — grill, pan-fry or boil for breakfast, a braai or a quick stew.",700,"kg","sausages.svg"],
  ["f32","fresh","Sausages","red","Pork Sausages","Seasoned pork sausages with a good fat balance — made for the grill and the frying pan.",750,"kg","sausages.svg"],
  ["f33","fresh","Sausages","red","Smokies (Pack of 10)","Small smoked sausages — the Kenyan street-food favourite, for smokie pasua or a lunchbox treat.",350,"pack","sausages.svg"],
  ["f34","fresh","Packs","red","Beef Stew Pack","1kg diced beef with tomatoes, onions and fresh dhania — everything for a family stew, one add-to-cart.",780,"pack","pack.svg"],
  ["f35","fresh","Packs",null,"Goat Stew Pack (Mbuzi)","1kg goat meat cut for the pot, with tomatoes, onions and dhania — ready for a slow, rich stew.",850,"pack","pack.svg"],
  ["f36","fresh","Packs","white","Chicken Stew Pack","A whole broiler cut up for stewing, with tomatoes, onions and dhania — dinner sorted.",650,"pack","pack.svg"],
  ["f37","fresh","Packs","white","Tilapia Fry Basket","1kg whole tilapia, scaled and gutted, with lemons, tomatoes, onions and dhania for kachumbari.",550,"basket","pack.svg"],
  ["f38","fresh","Packs","white","Fish Lover's Basket","1kg whole tilapia plus ½kg fresh omena and lemons — one basket, two favourites.",700,"basket","pack.svg"],
  ["f39","fresh","Packs","red","Bone Broth Bundle","1kg beef soup bones with fresh ginger and garlic — the start of a proper, restorative broth.",380,"bundle","pack.svg"],
  ["r9","ready","Grilled",null,"Smokie Pasua","A grilled smokie split open and loaded with fresh kachumbari — the classic Kenyan street bite.",100,"piece","grilled.svg"],
  ["r10","ready","Grilled",null,"Grilled Beef Sausage","Beef sausage charcoal-grilled to order, served with kachumbari.",150,"piece","grilled.svg"],
];

// How databases created earlier catch up with the menu: each time CATALOG_VERSION goes up,
// a database that already has products gets the newer rows added ONCE (never overwriting a
// row that exists, and never re-adding on later restarts — so an item you delete in admin
// afterwards stays deleted). A brand-new database simply gets everything at first start.
const CATALOG_VERSION = 2;
const V1_IDS = ['f28','f29','f30','r7','r8','g13','g14']; // the kienyeji / fish / eggs additions
const BROILER_NAME = 'Broiler Chicken (Whole)';
const BROILER_DESC = 'Standard commercial broiler, farm-fresh, cleaned and ready to cook — the everyday choice.';

db.exec(`CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)`);
const metaGet = (k) => { const r = db.prepare('SELECT value FROM meta WHERE key = ?').get(k); return r ? r.value : null; };
const metaSet = (k, v) => db.prepare('INSERT INTO meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value').run(k, String(v));

const insertSeed = db.prepare(`
  INSERT OR IGNORE INTO products (id, category_group, cat, kind, name, desc, price, unit, img, badges, origin)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '')
`);
const runSeed = (row) => {
  const [id, group, cat, kind, name, desc, price, unit, img, badges] = row;
  insertSeed.run(id, group, cat, kind, name, desc, price, unit, img, JSON.stringify(badges || []));
};

const productCount = db.prepare('SELECT COUNT(*) AS n FROM products').get().n;
if (productCount === 0) {
  db.transaction(() => {
    seedRows.forEach(runSeed);
    catalogV2Rows.forEach(runSeed);
    metaSet('catalog_version', CATALOG_VERSION);
  })();
} else if (Number(metaGet('catalog_version') || 0) < CATALOG_VERSION) {
  db.transaction(() => {
    seedRows.filter(r => V1_IDS.includes(r[0])).forEach(runSeed);
    catalogV2Rows.forEach(runSeed);
    // Only touch these two when they're still exactly as the first release left them.
    db.prepare("UPDATE products SET name = ?, desc = ? WHERE id = 'f6' AND name = 'Whole Chicken'").run(BROILER_NAME, BROILER_DESC);
    db.prepare(`UPDATE products SET badges = '["Free-Range"]' WHERE id IN ('f28','r8','g14') AND (badges IS NULL OR badges = '[]')`).run();
    metaSet('catalog_version', CATALOG_VERSION);
  })();
}

// First-run branch seed. The four original branches are listed on the site; the
// middle-to-high-end Nairobi branches below are seeded UNLISTED (listed = 0) on purpose —
// the pins are approximate and nothing should appear on the live site until the branch
// really exists. Tick "Show on website" in admin.html > Branches to publish one, and use
// the same screen to fix its address, map pin and phone number. Only runs on an empty
// table, so it never overwrites edits made in admin.
const branchCount = db.prepare('SELECT COUNT(*) AS n FROM branches').get().n;
if (branchCount === 0) {
  // [id, name, area, address, query, lat, lng, phone, hours, is_main, listed, sort_order]
  const branchSeed = [
    ["syokimau", "Syokimau", "Syokimau", "Syokimau, Nairobi", "Azizo Butchery and Grill, Syokimau, Nairobi, Kenya", -1.381, 36.927, "", "Open 24 hours", 1, 1, 1],
    ["umoja", "Umoja", "Umoja", "Umoja, Nairobi", "Azizo Butchery and Grill, Umoja, Nairobi, Kenya", -1.2833, 36.8956, "", "Open 24 hours", 0, 1, 2],
    ["buruburu", "Buruburu", "Buruburu", "Buruburu, Nairobi", "Azizo Butchery and Grill, Buruburu, Nairobi, Kenya", -1.2864, 36.879, "", "Open 24 hours", 0, 1, 3],
    ["utawala", "Utawala", "Utawala", "Utawala, Nairobi", "Azizo Butchery and Grill, Utawala, Nairobi, Kenya", -1.287, 36.96, "", "Open 24 hours", 0, 1, 4],
    ["kilimani", "Kilimani", "Kilimani", "Kilimani, Nairobi", "Azizo Butchery and Grill, Kilimani, Nairobi, Kenya", -1.2921, 36.7878, "", "Open 24 hours", 0, 0, 5],
    ["lavington", "Lavington", "Lavington", "Lavington, Nairobi", "Azizo Butchery and Grill, Lavington, Nairobi, Kenya", -1.279, 36.77, "", "Open 24 hours", 0, 0, 6],
    ["westlands", "Westlands", "Westlands", "Westlands, Nairobi", "Azizo Butchery and Grill, Westlands, Nairobi, Kenya", -1.2635, 36.803, "", "Open 24 hours", 0, 0, 7],
    ["kileleshwa", "Kileleshwa", "Kileleshwa", "Kileleshwa, Nairobi", "Azizo Butchery and Grill, Kileleshwa, Nairobi, Kenya", -1.276, 36.784, "", "Open 24 hours", 0, 0, 8],
    ["parklands", "Parklands", "Parklands", "Parklands, Nairobi", "Azizo Butchery and Grill, Parklands, Nairobi, Kenya", -1.262, 36.818, "", "Open 24 hours", 0, 0, 9],
    ["karen", "Karen", "Karen", "Karen, Nairobi", "Azizo Butchery and Grill, Karen, Nairobi, Kenya", -1.319, 36.709, "", "Open 24 hours", 0, 0, 10],
    ["runda", "Runda", "Runda", "Runda, Nairobi", "Azizo Butchery and Grill, Runda, Nairobi, Kenya", -1.218, 36.807, "", "Open 24 hours", 0, 0, 11],
    ["muthaiga", "Muthaiga", "Muthaiga", "Muthaiga, Nairobi", "Azizo Butchery and Grill, Muthaiga, Nairobi, Kenya", -1.253, 36.836, "", "Open 24 hours", 0, 0, 12],
    ["gigiri", "Gigiri", "Gigiri", "Gigiri, Nairobi", "Azizo Butchery and Grill, Gigiri, Nairobi, Kenya", -1.231, 36.804, "", "Open 24 hours", 0, 0, 13],
    ["south-c", "South C", "South C", "South C, Nairobi", "Azizo Butchery and Grill, South C, Nairobi, Kenya", -1.315, 36.826, "", "Open 24 hours", 0, 0, 14],
  ];
  const insertBranch = db.prepare(`
    INSERT INTO branches (id, name, area, address, query, lat, lng, phone, hours, is_main, listed, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  const seedBranches = db.transaction(() => { for (const row of branchSeed) insertBranch.run(...row); });
  seedBranches();
}

module.exports = db;

const express = require('express');
const db = require('../db');
const { requireAuth, requireAdmin } = require('../middleware/auth');
const router = express.Router();
router.post('/', (req, res) => {
  const { customer_name, customer_phone, fulfilment, branch, pickup_time, delivery_address,
    delivery_lat, delivery_lng, delivery_fee, payment_method, promo_code, items_subtotal, total, items } = req.body;
  if (!customer_phone || !fulfilment || !payment_method || !total || !Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: 'customer_phone, fulfilment, payment_method, total, and a non-empty items array are required' });
  }
  let customerId = null;
  const header = req.headers.authorization;
  if (header && header.startsWith('Bearer ')) {
    try {
      const jwt = require('jsonwebtoken');
      const { SECRET } = require('../middleware/auth');
      const decoded = jwt.verify(header.slice(7), SECRET);
      if (decoded.role === 'customer') customerId = decoded.id;
    } catch (e) {}
  }
  const insertOrder = db.prepare(`
    INSERT INTO orders (customer_id, customer_name, customer_phone, fulfilment, branch, pickup_time,
      delivery_address, delivery_lat, delivery_lng, delivery_fee, payment_method, promo_code, items_subtotal, total)
    VALUES (@customer_id, @customer_name, @customer_phone, @fulfilment, @branch, @pickup_time,
      @delivery_address, @delivery_lat, @delivery_lng, @delivery_fee, @payment_method, @promo_code, @items_subtotal, @total)
  `);
  const insertItem = db.prepare(`INSERT INTO order_items (order_id, product_id, name, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)`);
  const createOrder = db.transaction(() => {
    const result = insertOrder.run({ customer_id: customerId, customer_name: customer_name || null, customer_phone,
      fulfilment, branch: branch || null, pickup_time: pickup_time || null, delivery_address: delivery_address || null,
      delivery_lat: delivery_lat || null, delivery_lng: delivery_lng || null, delivery_fee: delivery_fee || 0,
      payment_method, promo_code: promo_code || null, items_subtotal: items_subtotal || total, total });
    const orderId = result.lastInsertRowid;
    for (const item of items) insertItem.run(orderId, item.id || null, item.name, item.qty, item.unit || '', item.price, item.price * item.qty);
    return orderId;
  });
  res.status(201).json({ ok: true, orderId: createOrder() });
});
// Orders change the moment staff touch them in admin.html, and the admin tool polls
// this right after every status update (including on a fresh page load) to show the
// current state — so this must never be served from a cache, browser or otherwise.
function noStore(req, res, next) { res.set('Cache-Control', 'no-store'); next(); }
router.get('/', requireAdmin, noStore, (req, res) => {
  const { status, phone } = req.query;
  let query = 'SELECT * FROM orders';
  const conditions = []; const params = [];
  if (status) { conditions.push('status = ?'); params.push(status); }
  if (phone) { conditions.push('customer_phone LIKE ?'); params.push(`%${phone}%`); }
  if (conditions.length) query += ' WHERE ' + conditions.join(' AND ');
  query += ' ORDER BY created_at DESC';
  res.json({ orders: db.prepare(query).all(...params) });
});
router.get('/me/history', requireAuth, noStore, (req, res) => {
  if (req.user.role !== 'customer') return res.status(403).json({ error: 'Customer login required' });
  res.json({ orders: db.prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC').all(req.user.id) });
});
// ---------- public order tracking ----------
// A customer looks their order up by its number only. The reply carries status information
// only (see below), and lookups are throttled per visitor so numbers can't be swept.
const trackHits = new Map(); // visitor -> timestamps of recent lookups
function trackLimiter(req, res, next) {
  const visitor = String(req.headers['x-forwarded-for'] || req.ip || '').split(',')[0].trim() || 'unknown';
  const now = Date.now();
  const recent = (trackHits.get(visitor) || []).filter(ts => now - ts < 10 * 60 * 1000);
  if (recent.length >= 30) return res.status(429).json({ error: 'Too many lookups — please wait a few minutes and try again' });
  recent.push(now);
  trackHits.set(visitor, recent);
  if (trackHits.size > 5000) { for (const [k, v] of trackHits) if (!v.some(ts => now - ts < 10 * 60 * 1000)) trackHits.delete(k); }
  next();
}
const last9 = (s) => String(s || '').replace(/\D/g, '').slice(-9);

router.get('/track', trackLimiter, noStore, (req, res) => {
  const id = Number(req.query.id);
  const notFound = () => res.status(404).json({ error: "We couldn't find an order with that number. Check it and try again." });
  if (!Number.isInteger(id) || id <= 0) return notFound();
  const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(id);
  if (!order) return notFound();
  // Order number alone is enough to track, so the reply is deliberately status-only: no name,
  // phone, address, items or prices — a stranger guessing numbers learns nothing personal.
  res.json({ order: { id: order.id, status: order.status, fulfilment: order.fulfilment, branch: order.branch,
    pickup_time: order.pickup_time, created_at: order.created_at } });
});

router.get('/:id', requireAdmin, noStore, (req, res) => {
  const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(req.params.id);
  if (!order) return res.status(404).json({ error: 'Order not found' });
  res.json({ order, items: db.prepare('SELECT * FROM order_items WHERE order_id = ?').all(req.params.id) });
});
router.patch('/:id/status', requireAdmin, (req, res) => {
  const { status } = req.body;
  const valid = ['received', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];
  if (!valid.includes(status)) return res.status(400).json({ error: `status must be one of: ${valid.join(', ')}` });
  const result = db.prepare('UPDATE orders SET status = ? WHERE id = ?').run(status, req.params.id);
  if (result.changes === 0) return res.status(404).json({ error: 'Order not found' });
  res.json({ ok: true });
});
module.exports = router;

const express = require('express');
const bcrypt = require('bcryptjs');
const db = require('../db');
const { signCustomerToken, signAdminToken } = require('../middleware/auth');
const router = express.Router();
router.post('/register', (req, res) => {
  const { name, phone, password } = req.body;
  if (!name || !phone || !password) return res.status(400).json({ error: 'name, phone, and password are all required' });
  if (password.length < 6) return res.status(400).json({ error: 'Password must be at least 6 characters' });
  const existing = db.prepare('SELECT id FROM customers WHERE phone = ?').get(phone);
  if (existing) return res.status(409).json({ error: 'An account with this phone number already exists' });
  const password_hash = bcrypt.hashSync(password, 10);
  const result = db.prepare('INSERT INTO customers (name, phone, password_hash) VALUES (?, ?, ?)').run(name, phone, password_hash);
  const customer = { id: result.lastInsertRowid, name, phone };
  res.status(201).json({ token: signCustomerToken(customer), customer });
});
router.post('/login', (req, res) => {
  const { phone, password } = req.body;
  if (!phone || !password) return res.status(400).json({ error: 'phone and password are required' });
  const customer = db.prepare('SELECT * FROM customers WHERE phone = ?').get(phone);
  if (!customer || !bcrypt.compareSync(password, customer.password_hash)) return res.status(401).json({ error: 'Incorrect phone number or password' });
  res.json({ token: signCustomerToken(customer), customer: { id: customer.id, name: customer.name, phone: customer.phone } });
});
router.post('/admin-login', (req, res) => {
  const { password } = req.body;
  if (!password || password !== process.env.ADMIN_PASSWORD) return res.status(401).json({ error: 'Incorrect admin password' });
  res.json({ token: signAdminToken() });
});
module.exports = router;

const jwt = require('jsonwebtoken');
const JWT_SECRET = process.env.JWT_SECRET;
const SECRET = JWT_SECRET || 'insecure-dev-only-secret-change-me';
function signCustomerToken(c) { return jwt.sign({ id: c.id, phone: c.phone, role: 'customer' }, SECRET, { expiresIn: '30d' }); }
function signAdminToken() { return jwt.sign({ role: 'admin' }, SECRET, { expiresIn: '12h' }); }
function requireAuth(req, res, next) {
  const h = req.headers.authorization;
  if (!h || !h.startsWith('Bearer ')) return res.status(401).json({ error: 'Missing or invalid Authorization header' });
  try { req.user = jwt.verify(h.slice(7), SECRET); next(); }
  catch (e) { return res.status(401).json({ error: 'Invalid or expired token' }); }
}
function requireAdmin(req, res, next) {
  requireAuth(req, res, () => {
    if (req.user.role !== 'admin') return res.status(403).json({ error: 'Admin access required' });
    next();
  });
}
module.exports = { signCustomerToken, signAdminToken, requireAuth, requireAdmin, SECRET };

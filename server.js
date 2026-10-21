require('dotenv').config();
const fs = require('fs');
const path = require('path');
const express = require('express');
const cors = require('cors');
const app = express();
app.use(cors());
// Review photos arrive as a (small, resized) data URL, so that one route may carry a bigger body.
app.use('/api/reviews', express.json({ limit: '1.5mb' }));
app.use(express.json());
// Uploaded product photos are served from here — e.g. an image saved as
// /uploads/f1-171234.jpg is reachable at <this server's URL>/uploads/f1-171234.jpg.
// On cPanel, this folder lives under your app's home directory and survives restarts
// as long as it isn't inside a directory your deploy process wipes on every push.
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));
// Local/offline testing only (e.g. on a phone via Termux): if a sibling "site" folder
// exists next to this backend folder, serve the static frontend from this same Node
// process so the whole website works from one URL (http://localhost:<PORT>) with no
// separate web server needed. On a real cPanel deploy, this folder normally isn't
// here (the frontend lives in public_html instead), so this simply does nothing.
const FRONTEND_DIR = path.join(__dirname, '..', 'site');
if (fs.existsSync(FRONTEND_DIR)) {
  app.use(express.static(FRONTEND_DIR));
}
app.get('/api/health', (req, res) => res.json({ ok: true, time: new Date().toISOString() }));
app.use('/api/products', require('./routes/products'));
app.use('/api/branches', require('./routes/branches'));
app.use('/api/areas', require('./routes/areas'));
app.use('/api/branch-stock', require('./routes/branchStock'));
app.use('/api/reviews', require('./routes/reviews'));
app.use('/api/reports', require('./routes/reports'));
app.use('/api/orders', require('./routes/orders'));
app.use('/api/auth', require('./routes/auth'));
app.use('/api/mpesa', require('./routes/mpesa'));
app.use((err, req, res, next) => {
  if (err && err.message && /image|Excel|xlsx|File too large/i.test(err.message)) {
    return res.status(400).json({ error: err.message });
  }
  console.error(err);
  res.status(500).json({ error: 'Something went wrong on the server' });
});
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Azizo backend running on port ${PORT}`));

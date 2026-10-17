require('dotenv').config();
const path = require('path');
const express = require('express');
const cors = require('cors');
const app = express();
app.use(cors());
app.use(express.json());
// Uploaded product photos are served from here — e.g. an image saved as
// /uploads/f1-171234.jpg is reachable at <this server's URL>/uploads/f1-171234.jpg.
// On cPanel, this folder lives under your app's home directory and survives restarts
// as long as it isn't inside a directory your deploy process wipes on every push.
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));
app.get('/api/health', (req, res) => res.json({ ok: true, time: new Date().toISOString() }));
app.use('/api/products', require('./routes/products'));
app.use('/api/branches', require('./routes/branches'));
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

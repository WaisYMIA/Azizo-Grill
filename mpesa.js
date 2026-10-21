const express = require('express');
const db = require('../db');
const router = express.Router();
const { MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_SHORTCODE, MPESA_PASSKEY, MPESA_ENV, MPESA_CALLBACK_URL } = process.env;
const BASE_URL = MPESA_ENV === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
function isConfigured() { return !!(MPESA_CONSUMER_KEY && MPESA_CONSUMER_SECRET && MPESA_SHORTCODE && MPESA_PASSKEY && MPESA_CALLBACK_URL); }
async function getAccessToken() {
  const auth = Buffer.from(`${MPESA_CONSUMER_KEY}:${MPESA_CONSUMER_SECRET}`).toString('base64');
  const res = await fetch(`${BASE_URL}/oauth/v1/generate?grant_type=client_credentials`, { headers: { Authorization: `Basic ${auth}` } });
  if (!res.ok) throw new Error(`Daraja auth failed: ${res.status}`);
  return (await res.json()).access_token;
}
function timestamp() {
  const d = new Date(); const p = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth()+1)}${p(d.getDate())}${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}
router.post('/stkpush', async (req, res) => {
  if (!isConfigured()) return res.status(503).json({ error: 'M-Pesa is not configured on this server yet. Add MPESA_* values to .env.' });
  const { phone, amount, orderId } = req.body;
  if (!phone || !amount) return res.status(400).json({ error: 'phone and amount are required' });
  try {
    const token = await getAccessToken();
    const ts = timestamp();
    const password = Buffer.from(`${MPESA_SHORTCODE}${MPESA_PASSKEY}${ts}`).toString('base64');
    const stkRes = await fetch(`${BASE_URL}/mpesa/stkpush/v1/processrequest`, {
      method: 'POST', headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ BusinessShortCode: MPESA_SHORTCODE, Password: password, Timestamp: ts,
        TransactionType: 'CustomerPayBillOnline', Amount: Math.round(amount), PartyA: phone, PartyB: MPESA_SHORTCODE,
        PhoneNumber: phone, CallBackURL: MPESA_CALLBACK_URL, AccountReference: `Azizo-${orderId || 'Order'}`,
        TransactionDesc: 'Azizo Butchery & Grill order' }),
    });
    const stkData = await stkRes.json();
    if (!stkRes.ok) return res.status(502).json({ error: 'Daraja rejected the STK push request', details: stkData });
    db.prepare(`INSERT INTO mpesa_transactions (order_id, checkout_request_id, phone, amount, status) VALUES (?, ?, ?, ?, 'pending')`)
      .run(orderId || null, stkData.CheckoutRequestID, phone, Math.round(amount));
    res.json({ ok: true, checkoutRequestId: stkData.CheckoutRequestID, message: 'STK push sent — customer should check their phone.' });
  } catch (err) {
    console.error('STK push error:', err);
    res.status(500).json({ error: 'Something went wrong sending the STK push', details: String(err) });
  }
});
router.post('/callback', (req, res) => {
  const stkCallback = req.body?.Body?.stkCallback;
  if (!stkCallback) return res.status(400).json({ error: 'Unexpected callback shape' });
  const { CheckoutRequestID, ResultCode, CallbackMetadata } = stkCallback;
  const success = ResultCode === 0;
  let mpesaReceipt = null;
  if (success && CallbackMetadata?.Item) mpesaReceipt = CallbackMetadata.Item.find(i => i.Name === 'MpesaReceiptNumber')?.Value || null;
  db.prepare(`UPDATE mpesa_transactions SET status = ?, mpesa_receipt = ?, raw_callback = ? WHERE checkout_request_id = ?`)
    .run(success ? 'success' : 'failed', mpesaReceipt, JSON.stringify(req.body), CheckoutRequestID);
  if (success) {
    const txn = db.prepare('SELECT order_id FROM mpesa_transactions WHERE checkout_request_id = ?').get(CheckoutRequestID);
    if (txn?.order_id) db.prepare('UPDATE orders SET mpesa_receipt = ? WHERE id = ?').run(mpesaReceipt, txn.order_id);
  }
  res.json({ ResultCode: 0, ResultDesc: 'Accepted' });
});
router.get('/status/:checkoutRequestId', (req, res) => {
  const txn = db.prepare('SELECT status, mpesa_receipt FROM mpesa_transactions WHERE checkout_request_id = ?').get(req.params.checkoutRequestId);
  if (!txn) return res.status(404).json({ error: 'Transaction not found' });
  res.json(txn);
});
module.exports = router;

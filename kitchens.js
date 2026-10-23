(function(){
  'use strict';
  var K = window.KK, $ = K.$, esc = K.esc, money = K.money;
  var CFG = null, AREAS = [], state = { area: K.get('azizoArea'), lat: null, lng: null, date: '', meal: '', trad: '', q: '' }, DATA = null, cart = { kitchen: null, items: [] }, openK = null, pending = null;
  var EMOJI = { 'Combo':'🍛','Plate':'🍽️','Family Pack':'🥘','Side':'🥬' };
  K.header('order'); K.footer();
  function show(el, on){ el.classList[on ? 'add' : 'remove']('show'); }
  document.addEventListener('click', function(e){ if(e.target.matches('[data-close]') || e.target.classList.contains('modal')) document.querySelectorAll('.modal.show').forEach(function(m){ m.classList.remove('show'); }); });
  function say(msg, kind){ var s = $('status'); if(!msg){ s.style.display = 'none'; return; } s.className = 'msg ' + (kind || 'info'); s.textContent = msg; s.style.display = ''; }
  function mealOfDay(){ var n = CFG.now.min; return n < 630 ? 'lunch' : (n < 990 ? 'dinner' : 'lunch'); }
  function addDays(d, n){ var x = new Date(d + 'T00:00:00Z'); x.setUTCDate(x.getUTCDate() + n); return x.toISOString().slice(0, 10); }
  function dayName(d, i){ return i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : new Date(d + 'T00:00:00Z').toLocaleDateString('en-KE', {weekday:'short', day:'numeric', month:'short', timeZone:'UTC'}); }

  async function init(){
    if(!K.base() && location.protocol === 'file:'){ say('Ordering needs the Azizo server. Ask us on WhatsApp, or open this page from the live site.', 'err'); return; }
    try{
      var r = await Promise.all([K.api('/config'), K.api('/areas')]); CFG = r[0]; AREAS = r[1].areas;
    }catch(e){ say(e.message + ' You can still order on WhatsApp: 0724 888 057.', 'err'); return; }
    $('area').innerHTML = K.areaOptions(AREAS, state.area); if(!AREAS.some(function(a){ return a.id === state.area; })) state.area = '';
    state.date = CFG.now.date; state.meal = mealOfDay();
    // if today's slot for that meal is closed, default to tomorrow
    var cutoff = CFG[state.meal].cutoff.split(':'); if(state.date === CFG.now.date && CFG.now.min >= (+cutoff[0]) * 60 + (+cutoff[1])){ state.date = addDays(CFG.now.date, 1); state.meal = 'lunch'; }
    drawChips(); $('area').onchange = function(){ state.area = this.value; state.lat = state.lng = null; K.set('azizoArea', state.area); search(); };
    $('gpsBtn').onclick = async function(){ try{ var p = await K.gps(); state.lat = p.lat; state.lng = p.lng; state.area = ''; $('area').value = ''; K.toast('Location found'); search(); }catch(e){ K.toast(e.message); } };
    $('search').oninput = function(){ state.q = this.value.trim().toLowerCase(); drawKitchens(); };
    $('trackBtn').onclick = openTrack; $('barBtn').onclick = openCart;
    search();
  }
  function drawChips(){
    $('dates').innerHTML = [0, 1, 2].map(function(i){ var d = addDays(CFG.now.date, i); return '<button class="chip' + (state.date === d ? ' on' : '') + '" data-d="' + d + '">' + dayName(d, i) + '</button>'; }).join('');
    $('meals').innerHTML = ['lunch', 'dinner'].map(function(m){ return '<button class="chip' + (state.meal === m ? ' on' : '') + '" data-m="' + m + '">' + (m === 'lunch' ? '☀️ ' : '🌙 ') + CFG[m].label + '<small>' + CFG[m].from + '–' + CFG[m].to + '</small></button>'; }).join('');
    $('dates').querySelectorAll('button').forEach(function(b){ b.onclick = function(){ state.date = b.dataset.d; drawChips(); search(); }; });
    $('meals').querySelectorAll('button').forEach(function(b){ b.onclick = function(){ state.meal = b.dataset.m; drawChips(); drawKitchens(); }; });
    $('slotNote').textContent = CFG[state.meal].label + ' is delivered ' + CFG[state.meal].from + ' to ' + CFG[state.meal].to + '. Order before ' + CFG[state.meal].cutoff + ' on the day.';
  }
  async function search(){
    drawChips(); DATA = null;
    if(!state.area && state.lat == null){ $('kitchens').innerHTML = '<div class="empty">Choose your area (or tap “Use my location”) to see kitchens that deliver to you.</div>'; $('trads').innerHTML = ''; say(''); return; }
    $('kitchens').innerHTML = '<div class="empty">Finding kitchens near you…</div>';
    try{
      var q = '/search?date=' + state.date + (state.area ? '&area=' + encodeURIComponent(state.area) : '&lat=' + state.lat + '&lng=' + state.lng);
      DATA = await K.api(q);
    }catch(e){ $('kitchens').innerHTML = ''; say(e.message, 'err'); return; }
    say(''); if(cart.kitchen && !DATA.kitchens.some(function(k){ return k.id === cart.kitchen; })){ cart = { kitchen: null, items: [] }; updateBar(); }
    drawKitchens();
  }
  function dishMatches(d){
    if(d.meal !== 'both' && d.meal !== state.meal) return false;
    if(state.trad && d.tradition !== state.trad) return false;
    if(state.q && (d.name + ' ' + d.description + ' ' + d.tradition).toLowerCase().indexOf(state.q) < 0) return false;
    return true;
  }
  function drawKitchens(){
    if(!DATA) return; var el = $('kitchens');
    var trads = {}; DATA.kitchens.forEach(function(k){ k.menu.forEach(function(d){ if(d.tradition && (d.meal === 'both' || d.meal === state.meal)) trads[d.tradition] = 1; }); });
    $('trads').innerHTML = Object.keys(trads).length ? '<button class="chip' + (!state.trad ? ' on' : '') + '" data-t="">All cuisines</button>' + Object.keys(trads).sort().map(function(t){ return '<button class="chip' + (state.trad === t ? ' on' : '') + '" data-t="' + esc(t) + '">' + esc(t) + '</button>'; }).join('') : '';
    $('trads').querySelectorAll('button').forEach(function(b){ b.onclick = function(){ state.trad = b.dataset.t; drawKitchens(); }; });
    if(!DATA.kitchens.length){
      el.innerHTML = '<div class="panel empty"><h3>No kitchens in your area yet</h3><p>We are opening new estates one by one. Know a great home cook? <a href="kitchen-partners.html">Invite them to join</a>, or order meat and ready meals from <a href="index.html">Azizo Butchery &amp; Grill</a>.</p></div>'; return;
    }
    var list = DATA.kitchens.map(function(k){ return { k: k, menu: k.menu.filter(dishMatches) }; }).filter(function(x){ return x.menu.length; });
    if(!list.length){ el.innerHTML = '<div class="empty">Nothing matches that for ' + esc(CFG[state.meal].label.toLowerCase()) + '. Try another meal, cuisine or search.</div>'; return; }
    el.innerHTML = list.map(function(x){
      var k = x.k, sl = k.slots[state.meal], ok = sl.open && sl.left > 0, isOpen = openK === k.id || list.length === 1;
      var badge = !sl.open ? '<span class="kbadge warn">' + esc(sl.why) + '</span>' : sl.left <= 0 ? '<span class="kbadge warn">Fully booked</span>' : '<span class="kbadge">' + sl.left + ' orders left</span>';
      return '<article class="kcard' + (isOpen ? ' open' : '') + '" data-k="' + k.id + '"><div class="khead"><div class="kava">' + esc((k.name || '?').charAt(0)) + '</div><div><h3>' + esc(k.name) + '</h3>' +
        '<div class="kmeta">Cooked by <b>' + esc(k.cook) + '</b> · ' + (k.distanceKm < 1 ? 'under 1 km' : k.distanceKm + ' km') + ' away · delivery <b>' + money(k.fee) + '</b>' + (k.ratingCount ? ' · <span class="star">★</span> ' + k.rating + ' (' + k.ratingCount + ')' : ' · New kitchen') + '</div>' +
        (k.specialities ? '<div class="kmeta">' + esc(k.specialities) + '</div>' : '') + '</div><div>' + badge + '</div></div>' +
        '<div class="kmenu">' + (k.bio ? '<p class="kmeta" style="margin-top:0">' + esc(k.bio) + '</p>' : '') + '<div class="dgrid">' + x.menu.map(function(d){ return dishCard(k, d, ok); }).join('') + '</div></div></article>';
    }).join('');
    el.querySelectorAll('.khead').forEach(function(h){ h.onclick = function(){ var id = +h.parentNode.dataset.k; openK = openK === id ? null : id; drawKitchens(); }; });
    el.querySelectorAll('[data-add]').forEach(function(b){ b.onclick = function(){ var p = b.dataset.add.split(':'); var k = DATA.kitchens.filter(function(z){ return z.id === +p[0]; })[0]; var d = k.menu.filter(function(z){ return z.id === +p[1]; })[0]; startAdd(k, d); }; });
  }
  function dishCard(k, d, ok){
    var img = d.image ? ' style="background-image:url(\'' + esc(d.image.charAt(0) === '/' ? K.base() + d.image : d.image) + '\')"' : '';
    return '<div class="dish"><div class="dimg"' + img + '>' + (d.image ? '' : (EMOJI[d.category] || '🍲')) + '</div><div class="dbody"><div><span class="tag">' + esc(d.tradition || d.category) + '</span>' + (d.serves > 1 ? '<span class="tag">Serves ' + d.serves + '</span>' : '') + '</div><h4>' + esc(d.name) + '</h4><p>' + esc(d.description) + '</p>' +
      '<div class="dfoot"><span class="price">' + money(d.price) + '</span><button class="btn sm gold" data-add="' + k.id + ':' + d.id + '"' + (ok ? '' : ' disabled') + '>Add</button></div></div></div>';
  }
  // ----- basket -----
  function startAdd(k, d){
    if(cart.kitchen && cart.kitchen !== k.id && cart.items.length){ if(!confirm('Your basket has food from another kitchen. Start a new basket with ' + k.name + '?')) return; cart = { kitchen: null, items: [] }; }
    if(!d.options || !d.options.length){ commit(k, d, {}, 1); return; }
    pending = { k: k, d: d }; var h = '<h3>' + esc(d.name) + '</h3><p class="kmeta">' + esc(d.description) + '</p>';
    d.options.forEach(function(g, gi){ h += '<h4 style="margin-top:12px">' + esc(g.name) + (g.required ? ' <small style="color:var(--red2)">required</small>' : ' <small style="color:var(--mute)">optional</small>') + '</h4>';
      g.choices.forEach(function(c, ci){ h += '<label class="opt"><input type="radio" name="g' + gi + '" value="' + esc(c.name) + '"' + (g.required && ci === 0 ? ' checked' : '') + '><span>' + esc(c.name) + '</span>' + (c.price ? '<em>+' + money(c.price) + '</em>' : '') + '</label>'; }); });
    h += '<div id="optErr"></div><button class="btn gold" id="optAdd" style="width:100%;margin-top:12px">Add to basket</button>'; $('optBody').innerHTML = h; show($('mOpt'), true);
    $('optAdd').onclick = function(){
      var sel = {}; var miss = '';
      d.options.forEach(function(g, gi){ var c = document.querySelector('input[name=g' + gi + ']:checked'); if(c) sel[g.name] = c.value; else if(g.required) miss = g.name; });
      if(miss){ $('optErr').innerHTML = '<div class="msg err">Please choose ' + esc(miss.toLowerCase()) + '.</div>'; return; }
      commit(k, d, sel, 1); show($('mOpt'), false);
    };
  }
  function unitPrice(d, sel){ var p = d.price; (d.options || []).forEach(function(g){ var c = g.choices.filter(function(x){ return x.name === sel[g.name]; })[0]; if(c) p += c.price || 0; }); return p; }
  function commit(k, d, sel, qty){
    cart.kitchen = k.id; var key = d.id + JSON.stringify(sel), ex = cart.items.filter(function(i){ return i.key === key; })[0];
    if(ex) ex.qty += qty; else cart.items.push({ key: key, d: d, sel: sel, qty: qty, unit: unitPrice(d, sel) }); K.toast(d.name + ' added'); updateBar();
  }
  function sub(){ return cart.items.reduce(function(s, i){ return s + i.unit * i.qty; }, 0); }
  function curK(){ return DATA && DATA.kitchens.filter(function(k){ return k.id === cart.kitchen; })[0]; }
  function updateBar(){
    var n = cart.items.reduce(function(s, i){ return s + i.qty; }, 0), b = $('bar'); if(!n){ b.classList.remove('show'); return; }
    var k = curK(); $('barTxt').textContent = n + (n === 1 ? ' item' : ' items') + ' · ' + money(sub()); $('barSub').textContent = k ? 'from ' + k.name : ''; b.classList.add('show');
  }
  function openCart(){
    var k = curK(); if(!k || !cart.items.length) return; var sl = k.slots[state.meal], fee = k.fee;
    var h = '<h3>Your basket</h3><p class="kmeta">' + esc(k.name) + ' · ' + esc(CFG[state.meal].label) + ' ' + esc(dayName(state.date, [0, 1, 2].filter(function(i){ return addDays(CFG.now.date, i) === state.date; })[0] || 0)) + ' · ' + CFG[state.meal].from + '–' + CFG[state.meal].to + '</p>';
    cart.items.forEach(function(i, ix){ h += '<div class="line"><div>' + esc(i.d.name) + '<small>' + esc(Object.keys(i.sel).map(function(g){ return i.sel[g]; }).join(', ')) + '</small></div><div style="text-align:right"><span class="qty"><button data-q="' + ix + ':-1" aria-label="Less">−</button>' + i.qty + '<button data-q="' + ix + ':1" aria-label="More">+</button></span><div>' + money(i.unit * i.qty) + '</div></div></div>'; });
    var s = sub(); h += '<div class="line"><span>Food</span><span>' + money(s) + '</span></div><div class="line"><span>Delivery (' + k.distanceKm + ' km)</span><span>' + money(fee) + '</span></div><div class="tot"><span>Total</span><span id="grand">' + money(s + fee) + '</span></div>';
    if(s < k.minOrder) h += '<div class="msg err">Minimum order is ' + money(k.minOrder) + ' of food. Add ' + money(k.minOrder - s) + ' more.</div>';
    h += '<div class="row"><div><label class="f" for="cName">Your name</label><input id="cName" autocomplete="name" value="' + esc(K.get('kkName')) + '"></div><div><label class="f" for="cPhone">Phone</label><input id="cPhone" type="tel" autocomplete="tel" placeholder="07XX XXX XXX" value="' + esc(K.get('kkPhone')) + '"></div></div>' +
      '<label class="f" for="cAddr" style="margin-top:10px">Delivery address (building, house, landmark)</label><input id="cAddr" autocomplete="street-address" value="' + esc(K.get('kkAddr')) + '">' +
      '<label class="f" for="cNote" style="margin-top:10px">Notes for the kitchen (optional)</label><textarea id="cNote" placeholder="e.g. less pepper, call when you arrive"></textarea>' +
      '<label class="f" style="margin-top:10px">Pay on delivery</label><label class="opt"><input type="radio" name="pay" value="cash" checked><span>Cash</span></label><label class="opt"><input type="radio" name="pay" value="mpesa"><span>M-Pesa (pay the rider)</span></label>' +
      '<input class="hp" id="cHp" tabindex="-1" autocomplete="off" aria-hidden="true"><div id="cErr"></div><button class="btn red" id="cGo" style="width:100%;margin-top:10px"' + ((s < k.minOrder || !sl.open || sl.left <= 0) ? ' disabled' : '') + '>Place order · ' + money(s + fee) + '</button>';
    $('cartBody').innerHTML = h; show($('mCart'), true);
    $('cartBody').querySelectorAll('[data-q]').forEach(function(b){ b.onclick = function(){ var p = b.dataset.q.split(':'), it = cart.items[+p[0]]; it.qty += +p[1]; if(it.qty <= 0) cart.items.splice(+p[0], 1); updateBar(); if(cart.items.length) openCart(); else show($('mCart'), false); }; });
    $('cGo').onclick = place;
  }
  async function place(){
    var k = curK(), name = $('cName').value.trim(), phone = $('cPhone').value.trim(), addr = $('cAddr').value.trim(), err = $('cErr'); err.innerHTML = '';
    if(name.length < 2 || phone.replace(/\D/g, '').length < 9 || addr.length < 4){ err.innerHTML = '<div class="msg err">Please fill in your name, phone and delivery address.</div>'; return; }
    var body = { kitchenId: k.id, name: name, phone: phone, address: addr, notes: $('cNote').value, date: state.date, meal: state.meal, payment: document.querySelector('input[name=pay]:checked').value, website: $('cHp').value,
      items: cart.items.map(function(i){ return { dishId: i.d.id, qty: i.qty, options: i.sel }; }) };
    if(state.area) body.areaId = state.area; else { body.lat = state.lat; body.lng = state.lng; }
    $('cGo').disabled = true; $('cGo').textContent = 'Placing order…';
    try{
      var r = await K.api('/orders', { method: 'POST', body: JSON.stringify(body) });
      K.set('kkName', name); K.set('kkPhone', phone); K.set('kkAddr', addr); K.set('kkLast', JSON.stringify({ id: r.id, phone: phone }));
      cart = { kitchen: null, items: [] }; updateBar(); show($('mCart'), false);
      $('doneBody').innerHTML = '<h3>Order received 🎉</h3><div class="msg ok">Order <b>#' + r.id + '</b> with <b>' + esc(r.kitchen) + '</b>. Total <b>' + money(r.total) + '</b> (delivery ' + money(r.fee) + '), delivered ' + esc(r.window) + '.</div><p class="kmeta">The kitchen will confirm shortly. Keep your phone nearby. You can track this order any time with your order number and phone.</p>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn gold" id="dTrack">Track order</button><a class="btn" target="_blank" rel="noopener" href="https://wa.me/254724888057?text=' + encodeURIComponent('Hi Azizo Kitchens, my order number is #' + r.id) + '">WhatsApp us</a></div>';
      show($('mDone'), true); $('dTrack').onclick = function(){ show($('mDone'), false); openTrack(r.id, phone); }; search();
    }catch(e){ err.innerHTML = '<div class="msg err">' + esc(e.message) + '</div>'; $('cGo').disabled = false; $('cGo').textContent = 'Try again'; }
  }
  // ----- tracking -----
  var LABEL = { new: 'Waiting for the kitchen to confirm', accepted: 'Confirmed. The cook has your order', preparing: 'Being cooked', ready: 'Ready. Waiting for the rider', out_for_delivery: 'On its way to you', delivered: 'Delivered. Enjoy!', cancelled: 'Cancelled', rejected: 'The kitchen could not take this order' };
  function openTrack(id, phone){
    var last = {}; try{ last = JSON.parse(K.get('kkLast') || '{}'); }catch(e){}
    if(typeof id !== 'string' && typeof id !== 'number') id = last.id || ''; phone = phone || last.phone || K.get('kkPhone');
    $('trackBody').innerHTML = '<h3>Track your order</h3><div class="row"><div><label class="f" for="tId">Order number</label><input id="tId" inputmode="numeric" value="' + esc(id) + '"></div><div><label class="f" for="tPh">Phone used to order</label><input id="tPh" type="tel" value="' + esc(phone) + '"></div></div><button class="btn gold" id="tGo" style="margin:12px 0">Check</button><div id="tOut"></div>';
    show($('mTrack'), true); $('tGo').onclick = check; if(id && phone) check();
  }
  async function check(){
    var out = $('tOut'); out.innerHTML = '';
    try{
      var o = await K.api('/orders/track?id=' + encodeURIComponent($('tId').value.trim()) + '&phone=' + encodeURIComponent($('tPh').value.trim()));
      var h = '<div class="msg ' + (o.status === 'delivered' ? 'ok' : (o.status === 'cancelled' || o.status === 'rejected') ? 'err' : 'info') + '"><b>' + esc(LABEL[o.status] || o.status) + '</b>' + (o.reason ? '<br>Reason: ' + esc(o.reason) : '') + '</div><p class="kmeta">' + esc(o.kitchen) + ' · ' + esc(o.date) + ' ' + esc(o.meal) + ' ' + esc(o.window) + ' · ' + (o.payment === 'mpesa' ? 'M-Pesa' : 'Cash') + ' on delivery</p>';
      o.items.forEach(function(i){ h += '<div class="line"><div>' + i.qty + ' × ' + esc(i.name) + '<small>' + esc(i.options) + '</small></div><span>' + money(i.total) + '</span></div>'; }); h += '<div class="tot"><span>Total</span><span>' + money(o.total) + '</span></div>';
      if(o.status === 'delivered' && !o.rating) h += '<h4>How was it?</h4><div class="stars" id="stars">' + [1, 2, 3, 4, 5].map(function(n){ return '<button data-s="' + n + '" aria-label="' + n + ' stars">★</button>'; }).join('') + '</div><textarea id="rv" placeholder="Tell others about the food (optional)"></textarea><button class="btn gold" id="rGo" style="margin-top:8px" disabled>Send rating</button>';
      if(o.rating) h += '<p class="kmeta">Your rating: ' + '★'.repeat(o.rating) + '. Thank you!</p>'; out.innerHTML = h;
      var sc = 0; out.querySelectorAll('[data-s]').forEach(function(b){ b.onclick = function(){ sc = +b.dataset.s; out.querySelectorAll('[data-s]').forEach(function(x){ x.classList.toggle('on', +x.dataset.s <= sc); }); $('rGo').disabled = false; }; });
      if($('rGo')) $('rGo').onclick = async function(){ try{ await K.api('/orders/' + o.id + '/rate', { method: 'POST', body: JSON.stringify({ phone: $('tPh').value, rating: sc, comment: $('rv').value }) }); K.toast('Thank you for rating!'); check(); }catch(e){ K.toast(e.message); } };
    }catch(e){ out.innerHTML = '<div class="msg err">' + esc(e.message) + '</div>'; }
  }
  init();
})();

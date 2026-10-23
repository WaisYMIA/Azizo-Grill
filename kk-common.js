// Azizo Kitchens shared helpers (plain global script)
(function(){
  'use strict';
  var K = window.KK = window.KK || {};
  K.esc = function(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
  K.money = function(n){ return 'KSh ' + Number(n || 0).toLocaleString('en-KE'); };
  K.$ = function(id){ return document.getElementById(id); };
  K.base = function(){ var b = ''; try{ b = (window.KK_CONFIG && window.KK_CONFIG.API_BASE_URL) || localStorage.getItem('azizoAdminBackendUrl') || ''; }catch(e){} return String(b).replace(/\/+$/, ''); };
  K.get = function(k){ try{ return localStorage.getItem(k) || ''; }catch(e){ return ''; } };
  K.set = function(k, v){ try{ localStorage.setItem(k, v); }catch(e){} };
  K.tokenKey = null;
  K.api = async function(path, opts, token){
    opts = opts || {};
    var h = Object.assign({}, opts.body ? {'Content-Type':'application/json'} : {}, token ? {'Authorization':'Bearer ' + token} : {});
    var res;
    try{ res = await fetch(K.base() + '/api/kitchens' + path, Object.assign({}, opts, {headers:h, cache:'no-store'})); }
    catch(e){ throw new Error('Cannot reach the server. Check your internet or the backend address.'); }
    var data = {}; try{ data = await res.json(); }catch(e){}
    if(!res.ok){ var er = new Error(data.error || ('Something went wrong (' + res.status + ')')); er.status = res.status; throw er; }
    return data;
  };
  K.toast = function(msg){ var t = K.$('kkToast'); if(!t){ t = document.createElement('div'); t.id = 'kkToast'; t.className = 'toast'; document.body.appendChild(t); } t.textContent = msg; t.classList.add('show'); clearTimeout(K._t); K._t = setTimeout(function(){ t.classList.remove('show'); }, 2800); };
  K.today = function(){ var d = new Date(Date.now() + 3 * 3600000); return d.toISOString().slice(0, 10); };
  // shrink a chosen photo to a JPEG data URL under maxKB
  K.readImage = function(file, maxEdge, maxKB){
    return new Promise(function(resolve, reject){
      if(!file) return resolve('');
      if(!/^image\//.test(file.type)) return reject(new Error('Please choose a photo (JPG or PNG)'));
      var fr = new FileReader();
      fr.onerror = function(){ reject(new Error('Could not read that file')); };
      fr.onload = function(){
        var img = new Image();
        img.onerror = function(){ reject(new Error('That file is not a usable image')); };
        img.onload = function(){
          var edge = maxEdge || 1400, q = 0.85, s = Math.min(1, edge / Math.max(img.width, img.height)), c = document.createElement('canvas');
          c.width = Math.round(img.width * s); c.height = Math.round(img.height * s); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          var out = c.toDataURL('image/jpeg', q);
          while(out.length * 0.75 > (maxKB || 1200) * 1024 && q > 0.3){ q -= 0.1; out = c.toDataURL('image/jpeg', q); }
          resolve(out);
        };
        img.src = fr.result;
      };
      fr.readAsDataURL(file);
    });
  };
  K.areaOptions = function(areas, selected){
    var g = {}; areas.forEach(function(a){ (g[a.region] = g[a.region] || []).push(a); });
    return '<option value="">Choose your area…</option>' + Object.keys(g).sort().map(function(r){
      return '<optgroup label="' + K.esc(r) + '">' + g[r].map(function(a){ return '<option value="' + K.esc(a.id) + '"' + (a.id === selected ? ' selected' : '') + '>' + K.esc(a.name) + '</option>'; }).join('') + '</optgroup>';
    }).join('');
  };
  K.gps = function(){ return new Promise(function(res, rej){ if(!navigator.geolocation) return rej(new Error('Location is not available on this device')); navigator.geolocation.getCurrentPosition(function(p){ res({lat:p.coords.latitude, lng:p.coords.longitude}); }, function(){ rej(new Error('We could not get your location. Please choose your area instead.')); }, {enableHighAccuracy:true, timeout:12000}); }); };
  K.header = function(active){
    var L = [['kitchens.html','Order food','order'],['kitchen-partners.html','Cook with Azizo','partners'],['kitchen-portal.html','Kitchen login','portal'],['index.html','Azizo Butchery','shop']];
    var el = K.$('kkHeader'); if(!el) return;
    el.innerHTML = '<header class="kk-nav"><div class="kk-nav-in"><a class="kk-brand" href="kitchens.html"><img src="logo.png" alt="Azizo logo"><b>Azizo <span>Kitchens</span></b></a>' +
      '<button class="kk-menu" aria-label="Menu" onclick="document.getElementById(\'kkLinks\').classList.toggle(\'open\')">&#9776;</button><nav class="kk-links" id="kkLinks">' +
      L.map(function(l){ return '<a href="' + l[0] + '" class="' + (l[2] === active ? 'on' : '') + (l[2] === 'partners' ? ' cta' : '') + '">' + l[1] + '</a>'; }).join('') + '</nav></div></header>';
  };
  K.footer = function(){
    var el = K.$('kkFooter'); if(!el) return;
    el.innerHTML = '<footer class="kk-foot"><div class="wrap"><b style="color:var(--gold2);font-family:Oswald;letter-spacing:.06em">AZIZO KITCHENS</b><p>Homemade Kenyan lunch and dinner from trusted home kitchens near you, marketed and quality-checked by Azizo Butchery &amp; Grill. Each kitchen is an independent partner business.</p>' +
      '<p><a href="kitchens.html">Order</a> · <a href="kitchen-partners.html">Cook with us</a> · <a href="kitchen-portal.html">Kitchen login</a> · <a href="index.html">Azizo Butchery &amp; Grill</a> · <a href="https://wa.me/254724888057" target="_blank" rel="noopener">WhatsApp 0724 888 057</a></p></div></footer>';
  };
})();

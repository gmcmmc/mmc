/* MMC ERP — javni konfigurator (/cjenik). Isti izračun radi i server (pub_compute u app/public_site.php); ovdje služi samo za prikaz uživo. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var dataEl = document.getElementById('pcData');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent), OLD = {};
  try { OLD = JSON.parse(document.getElementById('pcOld').textContent) || {}; } catch (e) {}
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function esc(s) { var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function fmt(n) {
    var s = (Math.round(n * 100) / 100).toFixed(2).split('.');
    s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return s[0] + ',' + s[1] + ' KM';
  }
  function unitLbl(n, u) { return String(u).toLowerCase() === 'sat' ? (n === 1 ? 'sat' : (n >= 2 && n <= 4 ? 'sata' : 'sati')) : u; }
  function round2(n) { return Math.round(n * 100) / 100; }
  function scrollToEl(el) { if (el) el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); }

  /* ---------- sidra unutar stranice: ne dodaju stavke u historiju, pa "Nazad" radi kako treba ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href^="#"]'); if (!a || a.getAttribute('href').length < 2) return;
    var t = document.getElementById(a.getAttribute('href').slice(1)); if (!t) return;
    e.preventDefault(); document.dispatchEvent(new Event('pc-close-sheet')); if (t.tagName === 'DETAILS') t.open = true;
    scrollToEl(t); if (history.replaceState) history.replaceState(null, '', a.getAttribute('href'));
  });

  /* ---------- moduli: otvaranje/zatvaranje ---------- */
  function openMod(k) { var d = document.getElementById('mod-' + k); if (d) d.open = true; }
  $$('[data-open]').forEach(function (a) { a.addEventListener('click', function () { openMod(a.getAttribute('data-open')); }); });
  if (/^#mod-/.test(location.hash)) openMod(location.hash.slice(5));
  window.addEventListener('hashchange', function () { if (/^#mod-/.test(location.hash)) openMod(location.hash.slice(5)); });
  var oa = $('#modOpenAll'), ca = $('#modCloseAll');
  if (oa) oa.addEventListener('click', function () { $$('.pc-mod').forEach(function (d) { d.open = true; }); });
  if (ca) ca.addEventListener('click', function () { $$('.pc-mod').forEach(function (d) { d.open = false; }); });

  /* ---------- konfigurator ---------- */
  var form = document.getElementById('cfgForm');
  if (form) {
    var sumBody = $('#sumBody'), sumBar = $('#sumBar'), sumBarTotal = $('#sumBarTotal');
    var RECO = { optika: ['optics'], moto: ['dms', 'service'], retail: [], other: [] };
    var lastProfile = (form.querySelector('[name=profile]:checked') || {}).value || 'retail';

    var radio = function (n) { var r = form.querySelector('[name="' + n + '"]:checked'); return r ? r.value : ''; };
    var pkgBy = function (c) { return D.packages.filter(function (p) { return p.code === c; })[0]; };
    var addonBy = function (k) { return D.addons.filter(function (a) { return a.key === k; })[0]; };
    var vertBy = function (k) { return D.verticals.filter(function (v) { return v.key === k; })[0]; };
    var vertInput = function (k) { return form.querySelector('[name="verticals[]"][value="' + k + '"]'); };
    var addonInput = function (k) { return form.querySelector('[name="addons[]"][value="' + k + '"]'); };

    function compute() {
      var pkg = pkgBy(radio('pkg')), cyc = radio('cycle') || 'ANNUAL', months = D.cycles[cyc].months;
      var r = { pkg: pkg, cyc: cyc, months: months, lines: [], once: 0, period: 0, list: 0, svc: 0 };
      if (!pkg) return r;
      if (pkg.once > 0) { r.lines.push({ k: 'once', l: 'Implementacija — paket ' + pkg.name, a: pkg.once }); r.once += pkg.once; }
      var pp = pkg.price['price_' + cyc]; r.period += pp; r.list += pkg.monthly * months;
      r.lines.push({ k: 'period', l: 'Paket ' + pkg.name + ' — ' + D.cycles[cyc].label, a: pp });
      $$('[name="addons[]"]:checked', form).forEach(function (i) {
        var a = addonBy(i.value); if (!a || i.disabled) return;
        if (a.once > 0) { r.lines.push({ k: 'once', l: 'Implementacija — ' + a.name, a: a.once }); r.once += a.once; }
        var ap = a.price['price_' + cyc]; r.period += ap; r.list += a.monthly * months;
        r.lines.push({ k: 'period', l: 'Dodatni modul: ' + a.name + ' — ' + D.cycles[cyc].label, a: ap });
      });
      $$('[name="verticals[]"]:checked', form).forEach(function (i) { var v = vertBy(i.value); if (v) r.lines.push({ k: 'quote', l: 'Vertikalni modul: ' + v.name, a: 0 }); });
      D.services.forEach(function (s) {
        var inp = form.querySelector('[data-svc="' + s.code + '"]'), h = Math.max(0, Math.min(500, parseInt(inp && inp.value, 10) || 0));
        if (h > 0) { var amt = round2(h * s.price); r.svc += amt; r.lines.push({ k: 'svc', l: s.name + ' — ' + h + ' ' + unitLbl(h, s.unit) + ' × ' + fmt(s.price), a: amt }); }
      });
      r.once = round2(r.once); r.period = round2(r.period); r.net = round2(r.once + r.period);
      r.vat = round2(r.net * D.vat / 100); r.gross = round2(r.net + r.vat);
      r.monthly = round2(r.period / months); r.saving = round2(Math.max(0, r.list - r.period));
      return r;
    }

    function syncDeps() {
      var pkg = pkgBy(radio('pkg')); if (!pkg) return;
      D.addons.forEach(function (a) {
        var inp = addonInput(a.key), warn = form.querySelector('[data-addon-warn="' + a.key + '"]'), row = form.querySelector('[data-addon-row="' + a.key + '"]');
        if (!inp) return;
        var miss = a.deps.filter(function (d) { return pkg.modules.indexOf(d) < 0; });
        inp.disabled = miss.length > 0; if (miss.length) inp.checked = false;
        if (row) row.classList.toggle('is-off', miss.length > 0);
        if (warn) { warn.hidden = !miss.length; warn.textContent = miss.length ? 'Zahtijeva module koje paket ' + pkg.name + ' ne uključuje (' + miss.map(function (m) { return D.modNames[m] || m; }).join(', ') + '). Odaberite viši paket.' : ''; }
      });
      D.verticals.forEach(function (v) {
        var inp = vertInput(v.key); if (!inp) return;
        var miss = v.deps.filter(function (d) { return pkg.modules.indexOf(d) < 0 && !vertBy(d); });
        inp.disabled = miss.length > 0; if (miss.length) inp.checked = false;
      });
      var dms = vertInput('dms'), svc = vertInput('service');
      if (dms && svc && dms.checked && !svc.disabled) svc.checked = true;
    }

    function labels() {
      var cyc = radio('cycle') || 'ANNUAL';
      D.packages.forEach(function (p) {
        var el = form.querySelector('[data-pack-price="' + p.code + '"]'); if (el) el.textContent = fmt(p.price['price_' + cyc]) + ' / ' + D.cycles[cyc].label.toLowerCase();
      });
      var pkg = pkgBy(radio('pkg'));
      Object.keys(D.cycles).forEach(function (c) {
        var el = form.querySelector('[data-cycle-hint="' + c + '"]'); if (!el || !pkg) return;
        var m = D.cycles[c].months, sv = round2(pkg.monthly * m - pkg.price['price_' + c]);
        el.textContent = fmt(pkg.price['price_' + c]) + (sv > 0.5 ? ' · ušteda ' + fmt(sv) : '');
      });
      D.addons.forEach(function (a) { var el = form.querySelector('[data-addon-price="' + a.key + '"]'); if (el) el.textContent = fmt(a.price['price_' + cyc]) + ' / ' + D.cycles[cyc].label.toLowerCase() + (a.once > 0 ? ' + ' + fmt(a.once) + ' jednokratno' : ''); });
    }

    function render() {
      syncDeps(); labels();
      var r = compute(); if (!sumBody) return;
      if (!r.pkg) { sumBody.innerHTML = '<p class="pc-hint">Odaberite paket.</p>'; return; }
      var h = '', once = r.lines.filter(function (x) { return x.k === 'once'; }), per = r.lines.filter(function (x) { return x.k === 'period'; }),
        quote = r.lines.filter(function (x) { return x.k === 'quote'; }), svc = r.lines.filter(function (x) { return x.k === 'svc'; });
      var row = function (x) { return '<li><span>' + esc(x.l) + '</span><b>' + fmt(x.a) + '</b></li>'; };
      if (once.length) h += '<h4>Jednokratno</h4><ul class="pc-sum-l">' + once.map(row).join('') + '</ul>';
      h += '<h4>Pretplata · ' + esc(D.cycles[r.cyc].label) + '</h4><ul class="pc-sum-l">' + per.map(row).join('') + '</ul>';
      if (quote.length) h += '<h4>Po dogovoru</h4><ul class="pc-sum-l">' + quote.map(function (x) { return '<li><span>' + esc(x.l) + '</span><b>cijena po dogovoru</b></li>'; }).join('') + '</ul>';
      h += '<div class="pc-sum-tot"><div><span>Ukupno bez PDV-a</span><b>' + fmt(r.net) + '</b></div><div><span>PDV (' + D.vat + '%)</span><b>' + fmt(r.vat) + '</b></div><div class="pc-sum-gross"><span>Ukupno sa PDV-om</span><b>' + fmt(r.gross) + '</b></div></div>';
      h += '<p class="pc-sum-eq">Pretplata u prosjeku <b>' + fmt(r.monthly) + '</b> mjesečno' + (r.saving > 0.5 ? ' · ušteda <b>' + fmt(r.saving) + '</b> u odnosu na mjesečno plaćanje' : '') + '.</p>';
      if (svc.length) h += '<h4>Procjena usluga (bez PDV-a)</h4><ul class="pc-sum-l">' + svc.map(row).join('') + '</ul><p class="pc-sum-eq">Usluge nisu uračunate u ukupno; ukupno procjena: <b>' + fmt(r.svc) + '</b>.</p>';
      sumBody.innerHTML = h;
      if (sumBarTotal) sumBarTotal.textContent = fmt(r.net);
    }

    form.addEventListener('change', function (e) {
      var t = e.target;
      if (t.name === 'profile') { applyProfile(t.value); }
      if (t.name === 'verticals[]' && t.value === 'dms' && t.checked) { var s = vertInput('service'); if (s) s.checked = true; }
      if (t.name === 'verticals[]' && t.value === 'service' && !t.checked) { var dm = vertInput('dms'); if (dm) dm.checked = false; }
      render();
    });
    form.addEventListener('input', function (e) { if (e.target.hasAttribute && e.target.hasAttribute('data-svc')) render(); });

    function applyProfile(p) {
      (RECO[lastProfile] || []).forEach(function (k) { var i = vertInput(k); if (i) i.checked = false; });
      (RECO[p] || []).forEach(function (k) { var i = vertInput(k); if (i && !i.disabled) i.checked = true; });
      var hint = $('#profileHint'), names = (RECO[p] || []).filter(function (k) { return k !== 'service' || RECO[p].length === 1; }).map(function (k) { return (vertBy(k) || {}).name; }).filter(Boolean);
      if (hint) { hint.hidden = !names.length; hint.textContent = names.length ? 'Za vašu djelatnost preporučujemo vertikalni modul: ' + names.join(', ') + '. Označili smo ga u koraku 5 — možete ga ukloniti.' : ''; }
      lastProfile = p;
    }

    /* paketi i moduli na stranici → konfigurator */
    $$('[data-pick]').forEach(function (b) { b.addEventListener('click', function () {
      var i = form.querySelector('[name=pkg][value="' + b.getAttribute('data-pick') + '"]'); if (i) { i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }
      scrollToEl(document.getElementById('konfigurator')); }); });
    $$('[data-add-addon]').forEach(function (b) { b.addEventListener('click', function () {
      var i = addonInput(b.getAttribute('data-add-addon')); if (!i) return;
      if (i.disabled) { var w = form.querySelector('[data-addon-warn="' + i.value + '"]'); alert(w ? w.textContent : 'Modul nije dostupan uz odabrani paket.'); }
      else { i.checked = true; render(); }
      scrollToEl(document.getElementById('konfigurator')); }); });
    $$('[data-add-vertical]').forEach(function (b) { b.addEventListener('click', function () {
      var i = vertInput(b.getAttribute('data-add-vertical')); if (i && !i.disabled) { i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }
      scrollToEl(document.getElementById('konfigurator')); }); });

    /* mobilna traka: ukupno + detalji konfiguracije u donjem panelu (zatvara se dugmetom, pozadinom ili tipkom Esc) */
    var sumToggle = $('#sumToggle'), sumPanel = $('#pcSum'), backdrop = $('#pcBackdrop'), sumClose = $('#sumClose');
    function sheet(open) {
      if (!sumPanel) return;
      sumPanel.classList.toggle('open', open);
      if (backdrop) backdrop.hidden = !open;
      document.body.classList.toggle('pc-sheet-open', open);
      if (sumToggle) { sumToggle.setAttribute('aria-expanded', open); sumToggle.textContent = open ? 'Zatvori' : 'Detalji'; }
    }
    if (sumToggle) sumToggle.addEventListener('click', function () { sheet(!sumPanel.classList.contains('open')); });
    document.addEventListener('pc-close-sheet', function () { sheet(false); });
    if (sumClose) sumClose.addEventListener('click', function () { sheet(false); });
    if (backdrop) backdrop.addEventListener('click', function () { sheet(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') sheet(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1000) sheet(false); });
    if ('IntersectionObserver' in window && sumBar) {
      var inCfg = false, inData = false, barUpd = function () { var show = inCfg && !inData; sumBar.hidden = !show; if (!show) sheet(false); };
      new IntersectionObserver(function (en) { inCfg = en[0].isIntersecting; barUpd(); }, { threshold: 0.05 }).observe(document.getElementById('konfigurator'));
      new IntersectionObserver(function (en) { inData = en[0].isIntersecting; barUpd(); }, { threshold: 0.2 }).observe(document.getElementById('podaci'));
    }
  }

  /* ---------- povrat unesenih vrijednosti nakon greške na serveru ---------- */
  (function applyOld() {
    if (!OLD || !OLD.kind) return;
    var f = document.getElementById(OLD.kind === 'ACCOUNTANT' ? 'accForm' : 'cfgForm'); if (!f) return;
    Object.keys(OLD).forEach(function (k) {
      var v = OLD[k]; if (k === 'kind' || k === 'csrf') return;
      if (Array.isArray(v)) { $$('[name="' + k + '[]"]', f).forEach(function (i) { i.checked = v.indexOf(i.value) >= 0; }); return; }
      if (v && typeof v === 'object') { Object.keys(v).forEach(function (c) { var i = f.querySelector('[name="' + k + '[' + c + ']"]'); if (i) i.value = v[c]; }); return; }
      $$('[name="' + k + '"]', f).forEach(function (i) {
        if (i.type === 'radio' || i.type === 'checkbox') i.checked = (i.value === String(v)); else i.value = v;
      });
    });
    var tgt = document.getElementById(f.id === 'accForm' ? 'knjigovodje' : 'konfigurator'); if (tgt) setTimeout(function () { scrollToEl(tgt); }, 60);
  })();
  if (form) { var evt = new Event('change', { bubbles: true }); var first = form.querySelector('[name=pkg]:checked'); if (first) first.dispatchEvent(evt); else form.dispatchEvent(evt); }

  /* ---------- provjera i slanje ---------- */
  $$('#cfgForm, #accForm').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!f.checkValidity()) { e.preventDefault(); f.reportValidity(); return; }
      var b = f.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.firstChild.textContent = 'Šaljem… '; }
    });
  });

  /* ---------- kalkulator za knjigovođe ---------- */
  var accEl = document.getElementById('accData'), cl = document.getElementById('accClientsCalc');
  if (accEl && cl) {
    var A = JSON.parse(accEl.textContent);
    var accUpd = function () {
      var n = Math.max(0, Math.min(500, parseInt(cl.value, 10) || 0));
      var c = document.getElementById('accCommission'), b = document.getElementById('accBonus');
      if (c) c.textContent = fmt(n * A.monthly * A.pct / 100) + ' / mj.';
      if (b) b.textContent = fmt(n * A.bonus);
    };
    cl.addEventListener('input', accUpd); accUpd();
  }

  window.addEventListener('pageshow', function (e) { if (e.persisted) $$('#cfgForm button[type=submit], #accForm button[type=submit]').forEach(function (b) { b.disabled = false; }); });
  var done = document.getElementById('hvala'); if (done) scrollToEl(done);
})();

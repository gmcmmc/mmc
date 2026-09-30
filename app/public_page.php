<?php
declare(strict_types=1);
/* MMC ERP Control Center 1.9.0 — prikaz javne stranice /cjenik (poziva se iz pub_entry()). */

function pub_icon(string $name): string {
  $p = [
    'check' => '<path d="M20 6 9 17l-5-5"/>', 'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>', 'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/>',
    'refresh' => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/>', 'cube' => '<path d="M12 2 3 7l9 5 9-5-9-5zM3 17l9 5 9-5M3 12l9 5 9-5"/>', 'receipt' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
    'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
  ];
  return '<svg class="pc-i" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}
function pub_money(float $v): string { return money_bam($v); }

function pub_render(array $d, array $errors, ?string $done): void {
  $base = pub_base(); $url = pub_url(); $ver = cc_version(); $defs = $d['defs']; $texts = $d['texts'];
  $site = pub_site_url(); $tok = pub_token(); $packs = $d['packages']; $addons = $d['addons']; $acc = $addons['accountant'] ?? null;
  $profiles = ['retail' => 'Trgovina / maloprodaja', 'optika' => 'Optika', 'moto' => 'Moto / vozila i servis', 'other' => 'Nešto drugo'];
  /* za povrat unesenih vrijednosti nakon greške na serveru */
  $old = []; foreach ($_POST as $k => $v) if (is_string($v) || is_array($v)) $old[$k] = $v; unset($old['t'], $old['website']);
  $title = 'MMC ERP — cjenik i konfigurator | M-M-C d.o.o. Orašje';
  $desc = 'Sastavite MMC ERP po mjeri: paketi, moduli i cijene na jednom mjestu. Pošaljite upit i javit ćemo vam se s ponudom.';
  $modGroups = []; foreach ($defs as $k => $m) $modGroups[$m['group']][$k] = $m;
  $posted = (string)($_POST['kind'] ?? '');
  $raw = fn(string $k): string => is_string($old[$k] ?? null) ? e($old[$k]) : '';
  $in = fn(string $k): string => $posted === 'ACCOUNTANT' ? '' : $raw($k);   /* obrazac za firme */
  $inA = fn(string $k): string => $posted === 'ACCOUNTANT' ? $raw($k) : ''; /* obrazac za knjigovođe */
  ?><!doctype html>
<html lang="bs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?></title><meta name="description" content="<?= e($desc) ?>"><meta name="theme-color" content="#061a2e">
<link rel="canonical" href="<?= e($site . '/cjenik') ?>"><meta property="og:type" content="website"><meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($desc) ?>"><meta property="og:url" content="<?= e($site . '/cjenik') ?>">
<link rel="icon" href="<?= e($base) ?>public/mmc-logo.png"><link rel="stylesheet" href="<?= e($base) ?>public/cjenik.css?v=<?= e($ver) ?>"></head>
<body class="pc">
<a class="pc-skip" href="#konfigurator">Preskoči na konfigurator</a>
<header class="pc-top"><div class="pc-wrap pc-top-in">
  <a class="pc-logo" href="<?= e($url) ?>" aria-label="MMC ERP"><img src="<?= e($base) ?>public/mmc-logo.png" alt="Mobile Media Centar" width="118" height="33"><span><b>MMC</b> <em>ERP</em></span></a>
  <nav class="pc-nav" aria-label="Sekcije"><a href="#paketi">Paketi</a><a href="#moduli">Moduli</a><a href="#konfigurator">Konfigurator</a><a href="#knjigovodje">Za knjigovođe</a><a href="#faq">Pitanja</a></nav>
  <a class="pc-btn pc-btn-primary pc-top-cta" href="#konfigurator">Sastavi konfiguraciju</a>
</div></header>

<?php if ($done): ?>
<div class="pc-wrap"><section class="pc-done" role="status" id="hvala"><div class="pc-done-ic"><?= pub_icon('check') ?></div><div><h2>Hvala, vaš upit je poslan.</h2><p>Broj vašeg upita je <b><?= e($done) ?></b>. Pregledat ćemo konfiguraciju i pitanja te vam se javiti s ponudom ili dodatnim pitanjima. Upit vas ni na šta ne obavezuje.</p></div></section></div>
<?php endif; ?>

<section class="pc-hero"><div class="pc-wrap pc-hero-in">
  <div class="pc-hero-txt"><span class="pc-kicker">MMC ERP PLATFORM</span>
    <h1>ERP sistem sastavljen po mjeri vaše firme.</h1>
    <p>Izaberite paket, dodajte module koji vam trebaju i odmah vidite cijenu. Kad ste zadovoljni, pošaljite upit — nema obaveze, a ponudu dobijate od tima koji sistem i razvija.</p>
    <div class="pc-hero-cta"><a class="pc-btn pc-btn-primary pc-btn-lg" href="#konfigurator">Sastavi konfiguraciju <?= pub_icon('arrow') ?></a><a class="pc-btn pc-btn-ghost pc-btn-lg" href="#knjigovodje">Za knjigovođe</a></div>
  </div>
  <ul class="pc-hero-points">
    <li><?= pub_icon('receipt') ?><div><b>Fiskalizacija i UIO</b><span>Datecs i Tring uređaji, KUF/KIF i eKUF/eKIF evidencije</span></div></li>
    <li><?= pub_icon('cube') ?><div><b>Moduli po potrebi</b><span>Plaćate ono što koristite, a dodajete kad porastete</span></div></li>
    <li><?= pub_icon('refresh') ?><div><b>Sigurne nadogradnje</b><span>Nove verzije centralno, uz sigurnosnu kopiju i provjeru rada</span></div></li>
    <li><?= pub_icon('shield') ?><div><b>Audit i kontrola</b><span>Zapis ko je šta promijenio i uloge s pravima pristupa</span></div></li>
  </ul>
</div></section>

<main>
<section class="pc-sec" id="paketi"><div class="pc-wrap">
  <div class="pc-sec-head"><span class="pc-kicker">PAKETI</span><h2>Izaberite polazište</h2><p>Svaki paket je potpun za svoju namjenu. Dodatne module dodajete po potrebi. Sve cijene su bez PDV-a.</p></div>
  <div class="pc-packs">
  <?php foreach ($packs as $p): ?>
    <article class="pc-pack" data-pack="<?= e($p['code']) ?>">
      <h3><?= e($p['name']) ?></h3><p class="pc-pack-desc"><?= e((string)($p['description'] ?? '')) ?></p>
      <div class="pc-pack-price"><b><?= pub_money((float)$p['price_MONTHLY']) ?></b><span>/ mjesečno</span></div>
      <div class="pc-pack-alt">
        <div><small>6 mjeseci</small><b><?= pub_money((float)$p['price_SEMIANNUAL']) ?></b></div>
        <div><small>12 mjeseci</small><b><?= pub_money((float)$p['price_ANNUAL']) ?></b><?php $sv = (float)$p['monthly_price'] * 12 - (float)$p['price_ANNUAL']; if ($sv > 0.5): ?><i>ušteda <?= pub_money($sv) ?></i><?php endif; ?></div>
      </div>
      <div class="pc-pack-meta"><span>Implementacija (jednokratno)</span><b><?= pub_money((float)$p['implementation_price']) ?></b></div>
      <?php if ((int)$p['support_minutes'] > 0): ?><div class="pc-pack-meta"><span>Podrška uključena</span><b><?= (int)$p['support_minutes'] ?> min / mj.</b></div><?php endif; ?>
      <ul class="pc-pack-mods"><?php foreach ($p['modules'] as $mk): ?><li><?= pub_icon('check') ?><a href="#mod-<?= e($mk) ?>" data-open="<?= e($mk) ?>"><?= e($defs[$mk]['name'] ?? $mk) ?></a></li><?php endforeach; ?></ul>
      <button type="button" class="pc-btn pc-btn-primary pc-pack-pick" data-pick="<?= e($p['code']) ?>">Izaberi paket <?= e($p['name']) ?></button>
    </article>
  <?php endforeach; ?>
  </div>
  <p class="pc-note">Dodatni moduli (Mobile Workspace, Advanced Warehouse, Management / BI, Centar knjigovođe) i vertikalni moduli za servis, moto i optiku biraju se u konfiguratoru.</p>
</div></section>

<section class="pc-sec pc-sec-alt" id="moduli"><div class="pc-wrap">
  <div class="pc-sec-head"><span class="pc-kicker">MODULI</span><h2>Šta tačno dobijate</h2><p>Kliknite na modul da vidite čemu služi, šta sadrži i za koga je. Moduli se oslanjaju jedan na drugi, pa sistem pazi da je sve što vam treba uključeno.</p>
    <div class="pc-tools"><button type="button" class="pc-btn pc-btn-ghost pc-btn-sm" id="modOpenAll">Otvori sve</button><button type="button" class="pc-btn pc-btn-ghost pc-btn-sm" id="modCloseAll">Zatvori sve</button></div></div>
  <?php foreach ($modGroups as $group => $mods): ?>
  <div class="pc-group"><h3><?= e($group) ?></h3>
    <?php foreach ($mods as $k => $m): $t = $texts[$k] ?? null; if (!$t) continue;
      $inPacks = []; foreach ($packs as $p) if (in_array($k, $p['modules'], true)) $inPacks[] = $p['name'];
      $isAdd = isset($addons[$k]); $isVert = isset($d['verticals'][$k]);
      $deps = array_values(array_diff(resolve_module_dependencies([$k]), [$k, 'core'])); ?>
    <details class="pc-mod" id="mod-<?= e($k) ?>">
      <summary><div class="pc-mod-h"><b><?= e($m['name']) ?></b><span><?= e($t['tagline']) ?></span></div>
        <div class="pc-mod-tag">
          <?php if ($isAdd): ?><em class="pc-tag pc-tag-add">Dodatni modul · <?= pub_money((float)$addons[$k]['monthly_price']) ?> / mj.</em>
          <?php elseif ($isVert): ?><em class="pc-tag pc-tag-vert">Vertikala · cijena po dogovoru</em>
          <?php elseif ($inPacks): ?><em class="pc-tag pc-tag-inc">Uključeno: <?= e(count($inPacks) === count($packs) ? 'svi paketi' : implode(', ', $inPacks)) ?></em><?php endif; ?>
        </div></summary>
      <div class="pc-mod-body">
        <div><h4>Čemu služi</h4><p><?= e($t['what']) ?></p>
          <h4>Za koga je</h4><p><?= e($t['for']) ?></p>
          <h4>Primjer iz prakse</h4><p class="pc-ex"><?= e($t['example']) ?></p></div>
        <div><h4>Šta dobijate</h4><ul class="pc-feat"><?php foreach ($t['features'] as $f): ?><li><?= pub_icon('check') ?><?= e($f) ?></li><?php endforeach; ?></ul>
          <?php if ($deps): ?><p class="pc-req"><b>Zahtijeva:</b> <?= e(implode(', ', array_map(fn($x) => $defs[$x]['name'] ?? $x, $deps))) ?></p><?php endif; ?>
          <?php if ($isAdd): $a = $addons[$k]; ?><p class="pc-req"><b>Cijena:</b> <?= pub_money((float)$a['monthly_price']) ?> mjesečno · <?= pub_money((float)$a['annual_price']) ?> godišnje<?= (float)$a['implementation_price'] > 0 ? ' · implementacija ' . pub_money((float)$a['implementation_price']) : '' ?> (bez PDV-a)</p>
            <button type="button" class="pc-btn pc-btn-primary pc-btn-sm" data-add-addon="<?= e($k) ?>">Dodaj u konfiguraciju</button>
          <?php elseif ($isVert): ?><button type="button" class="pc-btn pc-btn-primary pc-btn-sm" data-add-vertical="<?= e($k) ?>">Dodaj u konfiguraciju</button><?php endif; ?></div>
      </div>
    </details>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div></section>

<section class="pc-sec" id="konfigurator"><div class="pc-wrap">
  <div class="pc-sec-head"><span class="pc-kicker">KONFIGURATOR</span><h2>Sastavite svoj MMC ERP</h2><p>Ispod vidite cijenu dok birate. Na kraju unesite podatke o firmi i svoja pitanja, pa pošaljite upit.</p></div>

  <form method="post" action="<?= e($url) ?>" id="cfgForm" class="pc-cfg" novalidate>
    <input type="hidden" name="kind" value="COMPANY"><input type="hidden" name="t" value="<?= e($tok) ?>">
    <div class="pc-hp" aria-hidden="true"><label>Web stranica<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="pc-cfg-main">
      <?php if ($errors && $posted !== 'ACCOUNTANT'): ?><div class="pc-alert pc-alert-err" role="alert"><b>Upit nije poslan:</b><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <fieldset class="pc-step"><legend><i>1</i> Čime se bavite?</legend>
        <div class="pc-choices pc-choices-4"><?php foreach ($profiles as $pk => $pl): ?><label class="pc-choice"><input type="radio" name="profile" value="<?= e($pk) ?>" <?= $pk === 'retail' ? 'checked' : '' ?>><span><?= e($pl) ?></span></label><?php endforeach; ?></div>
        <p class="pc-hint" id="profileHint" hidden></p></fieldset>

      <fieldset class="pc-step"><legend><i>2</i> Izaberite paket</legend>
        <div class="pc-choices pc-choices-3"><?php foreach ($packs as $i => $p): ?>
          <label class="pc-choice pc-choice-pack"><input type="radio" name="pkg" value="<?= e($p['code']) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><b><?= e($p['name']) ?></b><small><?= e((string)($p['description'] ?? '')) ?></small><strong data-pack-price="<?= e($p['code']) ?>"></strong></span></label>
        <?php endforeach; ?></div></fieldset>

      <fieldset class="pc-step"><legend><i>3</i> Period plaćanja</legend>
        <div class="pc-choices pc-choices-3"><?php foreach (PUB_CYCLES as $ck => [$cl, $cm]): ?><label class="pc-choice"><input type="radio" name="cycle" value="<?= e($ck) ?>" <?= $ck === 'ANNUAL' ? 'checked' : '' ?>><span><b><?= e($cl) ?></b><small data-cycle-hint="<?= e($ck) ?>"></small></span></label><?php endforeach; ?></div></fieldset>

      <?php if ($addons): ?>
      <fieldset class="pc-step"><legend><i>4</i> Dodatni moduli <em>neobavezno</em></legend>
        <div class="pc-list"><?php foreach ($addons as $k => $a): $t = $texts[$k] ?? []; ?>
          <label class="pc-opt" data-addon-row="<?= e($k) ?>"><input type="checkbox" name="addons[]" value="<?= e($k) ?>"><span class="pc-opt-b"><b><?= e($a['name']) ?></b><small><?= e($t['tagline'] ?? (string)($a['description'] ?? '')) ?></small><small class="pc-opt-warn" data-addon-warn="<?= e($k) ?>" hidden></small><a href="#mod-<?= e($k) ?>" data-open="<?= e($k) ?>">Saznaj više</a></span><span class="pc-opt-p" data-addon-price="<?= e($k) ?>"></span></label>
        <?php endforeach; ?></div></fieldset>
      <?php endif; ?>

      <fieldset class="pc-step"><legend><i><?= $addons ? '5' : '4' ?></i> Vertikalni moduli <em>cijena po dogovoru</em></legend>
        <div class="pc-list"><?php foreach ($d['verticals'] as $k => $v): $t = $texts[$k] ?? []; ?>
          <label class="pc-opt"><input type="checkbox" name="verticals[]" value="<?= e($k) ?>"><span class="pc-opt-b"><b><?= e($v['name']) ?></b><small><?= e($t['tagline'] ?? '') ?></small><a href="#mod-<?= e($k) ?>" data-open="<?= e($k) ?>">Saznaj više</a></span><span class="pc-opt-p">po dogovoru</span></label>
        <?php endforeach; ?></div>
        <p class="pc-hint">Vertikalni moduli prilagođavaju sistem vašoj djelatnosti. Cijenu dogovaramo nakon upita.</p></fieldset>

      <?php if ($d['services']): ?>
      <fieldset class="pc-step"><legend><i><?= $addons ? '6' : '5' ?></i> Dodatne usluge <em>procjena u satima, neobavezno</em></legend>
        <div class="pc-list"><?php foreach ($d['services'] as $s): ?>
          <div class="pc-opt pc-opt-num"><span class="pc-opt-b"><b><?= e($s['name']) ?></b><small><?= e((string)($s['description'] ?? '')) ?></small></span><span class="pc-opt-p"><?= pub_money((float)$s['price']) ?> / <?= e($s['unit']) ?></span><label class="pc-num"><span class="pc-sr">Broj sati za <?= e($s['name']) ?></span><input type="number" min="0" max="500" step="1" value="0" inputmode="numeric" name="svc[<?= e($s['code']) ?>]" data-svc="<?= e($s['code']) ?>"><small><?= e($s['unit']) ?></small></label></div>
        <?php endforeach; ?></div>
        <p class="pc-hint">Usluge se ne uračunavaju u ukupnu cijenu ispod nego se prikazuju kao procjena. Tačan obim dogovaramo s vama.</p></fieldset>
      <?php endif; ?>

      <?php $n0 = 4 + ($addons ? 1 : 0) + ($d['services'] ? 1 : 0); ?>
      <fieldset class="pc-step"><legend><i><?= $n0 ?></i> O vašem poslovanju <em>pomaže nam da pripremimo ponudu</em></legend>
        <div class="pc-grid">
          <label>Broj korisnika sistema<input type="number" name="users_count" min="0" max="10000" inputmode="numeric" placeholder="npr. 5" value="<?= $in('users_count') ?>"></label>
          <label>Broj poslovnica / lokacija<input type="number" name="locations" min="0" max="1000" inputmode="numeric" placeholder="npr. 1" value="<?= $in('locations') ?>"></label>
          <label>Broj blagajni (POS)<input type="number" name="pos_count" min="0" max="1000" inputmode="numeric" placeholder="npr. 2" value="<?= $in('pos_count') ?>"></label>
          <label>Fiskalni uređaj<select name="fiscal_device"><option value="UNKNOWN">Ne znam / još nemam</option><option value="DATECS">Datecs</option><option value="TRING">Tring</option><option value="NONE">Ne koristim fiskalni uređaj</option></select></label>
          <label>Uvoz podataka iz postojećeg programa<select name="migration"><option value="UNKNOWN">Nisam siguran/a</option><option value="YES">Da, imam podatke koje želim prenijeti</option><option value="NO">Ne, počinjem ispočetka</option></select></label>
          <label>Sadašnji program / evidencija<input name="current_software" maxlength="120" placeholder="npr. Excel, Pantheon, ništa" value="<?= $in('current_software') ?>"></label>
          <label>Kada želite početi<select name="start_when"><option value="LATER">Još razmišljam</option><option value="ASAP">Što prije</option><option value="1M">U roku mjesec dana</option><option value="3M">U roku tri mjeseca</option></select></label>
        </div></fieldset>

      <fieldset class="pc-step" id="podaci"><legend><i><?= $n0 + 1 ?></i> Vaši podaci i pitanja</legend>
        <div class="pc-grid">
          <label>Naziv firme <b class="pc-req-s">*</b><input name="company_name" required maxlength="190" autocomplete="organization" value="<?= $in('company_name') ?>"></label>
          <label>Mjesto<input name="city" maxlength="120" autocomplete="address-level2" value="<?= $in('city') ?>"></label>
          <label>JIB<input name="jib" maxlength="30" inputmode="numeric" value="<?= $in('jib') ?>"></label>
          <label>PDV broj<input name="pdv" maxlength="30" inputmode="numeric" value="<?= $in('pdv') ?>"></label>
          <label>Kontakt osoba <b class="pc-req-s">*</b><input name="contact_name" required maxlength="160" autocomplete="name" value="<?= $in('contact_name') ?>"></label>
          <label>Telefon <b class="pc-req-s">*</b><input type="tel" name="phone" required maxlength="40" autocomplete="tel" value="<?= $in('phone') ?>"></label>
          <label class="pc-wide">Email <b class="pc-req-s">*</b><input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= $in('email') ?>"></label>
          <label class="pc-wide">Vaša pitanja i napomene<textarea name="message" rows="5" maxlength="4000" placeholder="Npr. kako izgleda uvoz artikala, treba li nam prilagodba dokumenata, imamo li posebne zahtjeve…"><?= $in('message') ?></textarea></label>
          <div class="pc-wide pc-pref"><span>Kako da vas kontaktiramo?</span><label><input type="radio" name="contact_pref" value="email" checked> Emailom</label><label><input type="radio" name="contact_pref" value="phone"> Telefonom</label></div>
        </div>
        <label class="pc-consent"><input type="checkbox" name="consent" value="1" required> <span>Saglasan/a sam da M-M-C d.o.o. Orašje obrađuje moje podatke isključivo radi odgovora na ovaj upit i pripreme ponude.</span></label>
        <button class="pc-btn pc-btn-primary pc-btn-lg pc-submit" type="submit" id="cfgSubmit">Pošalji upit <?= pub_icon('arrow') ?></button>
        <p class="pc-hint">Slanjem upita ništa ne naručujete. Javit ćemo vam se s ponudom ili pitanjima.</p>
      </fieldset>
    </div>

    <aside class="pc-sum" id="pcSum" aria-live="polite"><div class="pc-sum-in"><h3>Vaša konfiguracija</h3><div id="sumBody"><p class="pc-hint">Uključite JavaScript za prikaz cijene uživo. Upit možete poslati i bez toga — ukupnu cijenu izračunavamo mi.</p></div>
      <a class="pc-btn pc-btn-primary pc-sum-cta" href="#podaci">Nastavi na podatke <?= pub_icon('arrow') ?></a></div></aside>
  </form>
  <div class="pc-sumbar" id="sumBar" hidden><div><small>Ukupno bez PDV-a</small><b id="sumBarTotal">—</b></div><div class="pc-sumbar-act"><button type="button" class="pc-btn pc-btn-sm" id="sumToggle" aria-expanded="false" aria-controls="pcSum">Detalji</button><a class="pc-btn pc-btn-primary pc-btn-sm" href="#podaci">Pošalji upit</a></div></div>
</div></section>

<section class="pc-sec pc-sec-alt" id="knjigovodje"><div class="pc-wrap">
  <div class="pc-sec-head"><span class="pc-kicker">ZA KNJIGOVOĐE</span><h2>Partnerski program za knjigovodstvene agencije</h2>
    <p>Centar knjigovođe je modul u kojem vaš klijent s vama dijeli podatke iz MMC ERP-a: KUF/KIF i PDV pripremu, dokumente, napomene i korekcije — bez Excela i mailova naprijed-nazad.</p></div>
  <div class="pc-acc">
    <div class="pc-acc-info">
      <h3>Šta dobijate kao knjigovođa</h3>
      <ul class="pc-feat"><?php foreach ($texts['accountant']['features'] as $f): ?><li><?= pub_icon('check') ?><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php if ($acc): $pct = (float)$acc['partner_commission_pct']; $bonus = (float)$acc['referral_bonus']; ?>
      <h3>Kako funkcioniše saradnja</h3>
      <ol class="pc-steps"><li>Preporučite MMC ERP klijentu ili nas povežite s njim.</li><li>Firma aktivira modul Centar knjigovođe (<?= pub_money((float)$acc['monthly_price']) ?> mjesečno, <?= pub_money((float)$acc['annual_price']) ?> godišnje, bez PDV-a) — naplaćuje se klijentu, ne vama.</li>
        <?php if ($pct > 0 || $bonus > 0): ?><li>Vi dobijate <?php if ($pct > 0): ?>proviziju od <b><?= rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',') ?>%</b> na iznos modula<?php endif; ?><?= $pct > 0 && $bonus > 0 ? ' i ' : '' ?><?php if ($bonus > 0): ?>jednokratni bonus od <b><?= pub_money($bonus) ?></b> po aktiviranom klijentu<?php endif; ?>.</li><?php endif; ?>
        <li>Detalje obračuna i isplate dogovaramo pojedinačno.</li></ol>
      <?php if ($pct > 0): ?>
      <div class="pc-calc"><h4>Procjena zarade</h4><label>Koliko klijenata bi koristilo modul?<input type="number" id="accClientsCalc" min="1" max="500" value="10" inputmode="numeric"></label>
        <div class="pc-calc-out"><div><small>Mjesečna provizija</small><b id="accCommission">—</b></div><?php if ($bonus > 0): ?><div><small>Jednokratni bonusi</small><b id="accBonus">—</b></div><?php endif; ?></div>
        <p class="pc-hint">Okvirna procjena bez PDV-a. Konačni uslovi utvrđuju se partnerskim dogovorom.</p></div>
      <script type="application/json" id="accData"><?= json_encode(['monthly' => (float)$acc['monthly_price'], 'pct' => $pct, 'bonus' => $bonus], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      <?php endif; endif; ?>
      <p class="pc-req"><a href="#mod-accountant" data-open="accountant">Detaljan opis modula Centar knjigovođe</a></p>
    </div>
    <form method="post" action="<?= e($url) ?>#knjigovodje" class="pc-acc-form" id="accForm" novalidate>
      <input type="hidden" name="kind" value="ACCOUNTANT"><input type="hidden" name="t" value="<?= e($tok) ?>"><div class="pc-hp" aria-hidden="true"><label>Web stranica<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <h3>Zainteresovani ste? Pošaljite upit</h3>
      <?php if ($errors && $posted === 'ACCOUNTANT'): ?><div class="pc-alert pc-alert-err" role="alert"><b>Upit nije poslan:</b><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <div class="pc-grid">
        <label class="pc-wide">Naziv agencije / obrta <b class="pc-req-s">*</b><input name="company_name" required maxlength="190" autocomplete="organization" value="<?= $inA('company_name') ?>"></label>
        <label>Kontakt osoba <b class="pc-req-s">*</b><input name="contact_name" required maxlength="160" autocomplete="name" value="<?= $inA('contact_name') ?>"></label>
        <label>Mjesto<input name="city" maxlength="120" value="<?= $inA('city') ?>"></label>
        <label>Telefon <b class="pc-req-s">*</b><input type="tel" name="phone" required maxlength="40" autocomplete="tel" value="<?= $inA('phone') ?>"></label>
        <label>Email <b class="pc-req-s">*</b><input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= $inA('email') ?>"></label>
        <label class="pc-wide">Približno koliko firmi vodite?<input type="number" name="clients_count" min="1" max="5000" inputmode="numeric" placeholder="npr. 30" value="<?= $inA('clients_count') ?>"></label>
        <label class="pc-wide">Pitanja i napomene<textarea name="message" rows="4" maxlength="4000"><?= $inA('message') ?></textarea></label>
        <div class="pc-wide pc-pref"><span>Kako da vas kontaktiramo?</span><label><input type="radio" name="contact_pref" value="email" checked> Emailom</label><label><input type="radio" name="contact_pref" value="phone"> Telefonom</label></div>
      </div>
      <label class="pc-consent"><input type="checkbox" name="consent" value="1" required> <span>Saglasan/a sam da M-M-C d.o.o. Orašje obrađuje moje podatke isključivo radi odgovora na ovaj upit.</span></label>
      <button class="pc-btn pc-btn-primary pc-btn-lg pc-submit" type="submit">Pošalji upit <?= pub_icon('arrow') ?></button>
    </form>
  </div>
</div></section>

<section class="pc-sec" id="faq"><div class="pc-wrap pc-faq-wrap">
  <div class="pc-sec-head"><span class="pc-kicker">PITANJA</span><h2>Najčešća pitanja</h2></div>
  <div class="pc-faq"><?php foreach (pub_faq() as [$qq, $aa]): ?><details><summary><?= e($qq) ?></summary><p><?= e($aa) ?></p></details><?php endforeach; ?></div>
</div></section>
</main>

<footer class="pc-foot"><div class="pc-wrap pc-foot-in"><div><b>M-M-C d.o.o. Orašje</b><span>Mobile Media Centar · <a href="https://www.m-m-c.ba" rel="noopener">www.m-m-c.ba</a></span></div><div class="pc-foot-note">Sve cijene su u KM i bez PDV-a (<?= e(number_format(PUB_VAT, 0)) ?>%). Cijene i sadržaj paketa podložni su promjeni; važeća je pisana ponuda.</div></div></footer>

<script type="application/json" id="pcData"><?= json_encode(pub_js_data($d), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="pcOld"><?= json_encode($old ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e($base) ?>public/cjenik.js?v=<?= e($ver) ?>" defer></script>
</body></html><?php
}

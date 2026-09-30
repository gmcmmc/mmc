<?php
declare(strict_types=1);
/*
 * MMC ERP Control Center 1.9.0 — javni cjenik i konfigurator (/cjenik) + upiti.
 * Cijene se uvijek čitaju iz Cjenika u Control Centru (price_packages, commercial_modules, service_catalog);
 * konačni iznos se na serveru ponovno izračunava iz baze, nikad se ne vjeruje iznosu iz preglednika.
 */
require_once __DIR__ . '/public_catalog.php';

const PUB_VAT = 17.0;
/* period => [naziv, broj mjeseci] */
const PUB_CYCLES = ['MONTHLY' => ['Mjesečno', 1], 'SEMIANNUAL' => ['6 mjeseci', 6], 'ANNUAL' => ['12 mjeseci', 12]];
const PUB_RATE_MAX = 5;        /* upita po IP adresi */
const PUB_RATE_WINDOW = 3600;  /* u sekundama */

function inquiry_statuses(): array {
  return ['NEW' => 'Novi', 'IN_PROGRESS' => 'U obradi', 'OFFER_SENT' => 'Ponuda poslana', 'WON' => 'Dobijeno', 'LOST' => 'Izgubljeno', 'SPAM' => 'Spam'];
}
function inquiry_status_badge(string $s): string {
  $kind = match ($s) { 'NEW' => 'warn', 'WON' => 'ok', 'SPAM', 'LOST' => 'err', default => 'ok' };
  return badge(inquiry_statuses()[$s] ?? $s, $kind);
}

function inquiry_schema(): void {
  static $done = false; if ($done) return; $done = true;
  db()->exec("CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY, ref_no VARCHAR(30) NOT NULL UNIQUE, kind VARCHAR(20) NOT NULL DEFAULT 'COMPANY', status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    company_name VARCHAR(190) NOT NULL, jib VARCHAR(30) NULL, pdv VARCHAR(30) NULL, city VARCHAR(120) NULL,
    contact_name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(60) NOT NULL,
    profile VARCHAR(30) NULL, package_code VARCHAR(40) NULL, billing_cycle VARCHAR(20) NULL,
    total_once DECIMAL(12,2) NOT NULL DEFAULT 0, total_period DECIMAL(12,2) NOT NULL DEFAULT 0, total_net DECIMAL(12,2) NOT NULL DEFAULT 0, total_gross DECIMAL(12,2) NOT NULL DEFAULT 0,
    message TEXT NULL, details_json LONGTEXT NULL, config_json LONGTEXT NULL, internal_note TEXT NULL, offer_id INT NULL, notified TINYINT NOT NULL DEFAULT 0,
    consent_at DATETIME NULL, ip_hash CHAR(64) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    INDEX(status), INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function inquiry_new_count(): int {
  try { inquiry_schema(); return (int)q("SELECT COUNT(*) FROM inquiries WHERE status='NEW'")->fetchColumn(); } catch (Throwable $e) { return 0; }
}

/* ---------- URL-ovi ---------- */
function pub_base(): string { $d = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/'); return $d . '/'; }
function pub_url(): string { return str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), 'page=cjenik') ? pub_base() . '?page=cjenik' : pub_base() . 'cjenik'; }
function pub_site_url(): string { $u = local_cfg()['app_url'] ?? 'https://erp.mmc.ba'; return rtrim(is_string($u) && preg_match('~^https?://[^\s"<>]+$~i', $u) ? $u : 'https://erp.mmc.ba', '/'); }

/* ---------- IP, potpisani token, ograničenje broja upita ---------- */
function pub_secret(): string { $c = local_cfg(); return hash('sha256', 'mmc-pub|' . ($c['db_pass'] ?? '') . '|' . ($c['db_name'] ?? '')); }
/* Bez sesije: token = vrijeme izdavanja + potpis. Forma prebrzo (<4 s) ili prestara (>12 h) se odbija. */
function pub_token(): string { $t = (string)time(); return $t . '.' . hash_hmac('sha256', $t, pub_secret()); }
function pub_token_check(string $tok): ?string {
  $p = explode('.', $tok, 2); if (count($p) !== 2 || !ctype_digit($p[0])) return 'Sigurnosni token nije ispravan. Osvježite stranicu i pokušajte ponovo.';
  if (!hash_equals(hash_hmac('sha256', $p[0], pub_secret()), $p[1])) return 'Sigurnosni token nije ispravan. Osvježite stranicu i pokušajte ponovo.';
  $age = time() - (int)$p[0];
  if ($age < 4) return 'Forma je poslana prebrzo. Pokušajte ponovo za nekoliko sekundi.';
  if ($age > 43200) return 'Forma je istekla. Osvježite stranicu (vaša konfiguracija se može ponovo odabrati) i pošaljite ponovo.';
  return null;
}
function pub_rate_file(): string { return __DIR__ . '/../storage/inquiry-throttle.json'; }
function pub_rate_load(): array { $f = pub_rate_file(); $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : []; $d = is_array($d) ? $d : []; $now = time(); return array_map(fn($a) => array_values(array_filter((array)$a, fn($t) => $now - (int)$t < PUB_RATE_WINDOW)), $d); }
function pub_rate_ok(): bool { $d = pub_rate_load(); return count($d['ip:' . hash('sha256', client_ip())] ?? []) < PUB_RATE_MAX && count($d['all'] ?? []) < 60; }
function pub_rate_hit(): void {
  $d = pub_rate_load(); foreach (['ip:' . hash('sha256', client_ip()), 'all'] as $k) $d[$k][] = time();
  $dir = dirname(pub_rate_file()); if (is_dir($dir) && is_writable($dir)) @file_put_contents(pub_rate_file(), json_encode(array_filter($d)), LOCK_EX);
}

/* ---------- Podaci za stranicu ---------- */
/* Moduli uključeni u pojedini paket (prema šifri paketa iz Cjenika). Nepoznata šifra = START. */
function pub_package_module_keys(string $code): array {
  $pr = module_presets();
  $keys = match (strtoupper($code)) { 'BUSINESS', 'PRO' => $pr['BUSINESS'], default => $pr['START'] };
  return resolve_module_dependencies($keys);
}
/* 1 sat, 2-4 sata, ostalo sati; druge jedinice ostaju kako su upisane u cjeniku. */
function pub_unit(int $n, string $unit): string { return strtolower($unit) === 'sat' ? ($n === 1 ? 'sat' : ($n >= 2 && $n <= 4 ? 'sata' : 'sati')) : $unit; }
function pub_price(array $row, string $cycle, bool $pack): float {
  $m = (float)$row['monthly_price']; $a = (float)$row['annual_price'];
  return round(match ($cycle) {
    'MONTHLY' => $m,
    'ANNUAL' => $a > 0 ? $a : $m * 12,
    default => $pack && (float)$row['semiannual_price'] > 0 ? (float)$row['semiannual_price'] : $m * 6,
  }, 2);
}
function pub_data(): array {
  commercial_schema();
  $defs = module_definitions(); $texts = pub_module_texts();
  $packages = [];
  foreach (q('SELECT * FROM price_packages WHERE active=1 ORDER BY sort_order,id')->fetchAll() as $p) {
    $p['code'] = strtoupper((string)$p['code']); $p['modules'] = pub_package_module_keys($p['code']);
    foreach (array_keys(PUB_CYCLES) as $c) $p['price_' . $c] = pub_price($p, $c, true);
    $packages[] = $p;
  }
  $addons = [];
  foreach (q('SELECT * FROM commercial_modules WHERE active=1 ORDER BY sort_order,id')->fetchAll() as $a) {
    $k = (string)$a['module_key']; if (!isset($defs[$k])) continue;
    $a['deps'] = array_values(array_diff(resolve_module_dependencies([$k]), [$k]));
    foreach (array_keys(PUB_CYCLES) as $c) $a['price_' . $c] = pub_price($a, $c, false);
    $addons[$k] = $a;
  }
  $verticals = [];
  foreach ($defs as $k => $d) if ($d['group'] === 'Vertikale') $verticals[$k] = ['key' => $k, 'name' => $d['name'], 'deps' => array_values(array_diff(resolve_module_dependencies([$k]), [$k]))];
  $services = q('SELECT * FROM service_catalog WHERE active=1 ORDER BY sort_order,id')->fetchAll();
  return ['packages' => $packages, 'addons' => $addons, 'verticals' => $verticals, 'services' => $services, 'defs' => $defs, 'texts' => $texts];
}
/* Podaci koje koristi JavaScript konfigurator (isti izračun kao pub_compute). */
function pub_js_data(array $d): array {
  return [
    'vat' => PUB_VAT, 'cycles' => array_map(fn($c) => ['label' => $c[0], 'months' => $c[1]], PUB_CYCLES),
    'packages' => array_map(fn($p) => ['code' => $p['code'], 'name' => $p['name'], 'once' => (float)$p['implementation_price'], 'monthly' => (float)$p['monthly_price'], 'price' => array_intersect_key($p, array_flip(array_map(fn($c) => 'price_' . $c, array_keys(PUB_CYCLES)))), 'modules' => $p['modules'], 'support' => (int)$p['support_minutes']], $d['packages']),
    'addons' => array_map(fn($a) => ['key' => $a['module_key'], 'name' => $a['name'], 'once' => (float)$a['implementation_price'], 'monthly' => (float)$a['monthly_price'], 'price' => array_intersect_key($a, array_flip(array_map(fn($c) => 'price_' . $c, array_keys(PUB_CYCLES)))), 'deps' => $a['deps']], array_values($d['addons'])),
    'verticals' => array_values($d['verticals']),
    'services' => array_map(fn($s) => ['code' => $s['code'], 'name' => $s['name'], 'unit' => $s['unit'], 'price' => (float)$s['price']], $d['services']),
    'modNames' => array_map(fn($x) => $x['name'], $d['defs']),
  ];
}

/* ---------- Izračun konfiguracije (autoritativan, na serveru) ---------- */
function pub_compute(array $in, array $d): array {
  $err = []; $lines = [];
  $code = strtoupper(trim((string)($in['pkg'] ?? '')));
  $pack = null; foreach ($d['packages'] as $p) if ($p['code'] === $code) { $pack = $p; break; }
  if (!$pack) $err[] = 'Odaberite paket.';
  $cycle = strtoupper((string)($in['cycle'] ?? 'ANNUAL')); if (!isset(PUB_CYCLES[$cycle])) $cycle = 'ANNUAL';
  [$cycleLabel, $months] = PUB_CYCLES[$cycle];
  $once = 0.0; $period = 0.0; $list = 0.0; $addonKeys = []; $vertKeys = [];
  if ($pack) {
    if ((float)$pack['implementation_price'] > 0) { $lines[] = ['k' => 'once', 'label' => 'Implementacija — paket ' . $pack['name'], 'amount' => round((float)$pack['implementation_price'], 2)]; $once += (float)$pack['implementation_price']; }
    $pp = $pack['price_' . $cycle]; $period += $pp; $list += (float)$pack['monthly_price'] * $months;
    $lines[] = ['k' => 'period', 'label' => 'Paket ' . $pack['name'] . ' — ' . $cycleLabel, 'amount' => $pp];
    foreach ((array)($in['addons'] ?? []) as $k) {
      if (!is_scalar($k)) continue; $k = (string)$k; if (!isset($d['addons'][$k]) || in_array($k, $addonKeys, true)) continue; $a = $d['addons'][$k];
      $miss = array_diff($a['deps'], $pack['modules']);
      if ($miss) { $err[] = 'Modul „' . $a['name'] . '“ zahtijeva module koje paket ' . $pack['name'] . ' ne uključuje (' . implode(', ', array_map(fn($m) => $d['defs'][$m]['name'] ?? $m, $miss)) . '). Odaberite viši paket.'; continue; }
      $addonKeys[] = $k;
      if ((float)$a['implementation_price'] > 0) { $lines[] = ['k' => 'once', 'label' => 'Implementacija — ' . $a['name'], 'amount' => round((float)$a['implementation_price'], 2)]; $once += (float)$a['implementation_price']; }
      $ap = $a['price_' . $cycle]; $period += $ap; $list += (float)$a['monthly_price'] * $months;
      $lines[] = ['k' => 'period', 'label' => 'Dodatni modul: ' . $a['name'] . ' — ' . $cycleLabel, 'amount' => $ap];
    }
    $want = []; foreach ((array)($in['verticals'] ?? []) as $k) if (is_scalar($k) && isset($d['verticals'][(string)$k])) $want[] = (string)$k;
    foreach (resolve_module_dependencies($want) as $k) if (isset($d['verticals'][$k])) $vertKeys[] = $k;
    foreach ($vertKeys as $k) {
      $miss = array_diff(array_diff($d['verticals'][$k]['deps'], $vertKeys), $pack['modules']);
      if ($miss) { $err[] = 'Vertikalni modul „' . $d['verticals'][$k]['name'] . '“ zahtijeva module koje paket ne uključuje. Odaberite viši paket.'; continue; }
      $lines[] = ['k' => 'quote', 'label' => 'Vertikalni modul: ' . $d['verticals'][$k]['name'], 'amount' => 0.0, 'note' => 'cijena po dogovoru'];
    }
  }
  $svc = []; $svcNet = 0.0; $svcIn = is_array($in['svc'] ?? null) ? $in['svc'] : [];
  foreach ($d['services'] as $s) {
    $h = (int)($svcIn[$s['code']] ?? 0); $h = max(0, min(500, $h)); if ($h <= 0) continue;
    $amt = round($h * (float)$s['price'], 2); $svc[$s['code']] = $h; $svcNet += $amt;
    $lines[] = ['k' => 'svc', 'label' => $s['name'] . ' — ' . $h . ' ' . pub_unit($h, (string)$s['unit']) . ' × ' . money_bam((float)$s['price']), 'amount' => $amt];
  }
  $once = round($once, 2); $period = round($period, 2); $net = round($once + $period, 2); $vat = round($net * PUB_VAT / 100, 2);
  return ['errors' => array_values(array_unique($err)), 'pkg' => $pack ? ['code' => $pack['code'], 'name' => $pack['name']] : null, 'cycle' => $cycle, 'cycle_label' => $cycleLabel, 'months' => $months,
    'lines' => $lines, 'once' => $once, 'period' => $period, 'net' => $net, 'vat' => $vat, 'gross' => round($net + $vat, 2), 'monthly' => round($period / max(1, $months), 2),
    'saving' => round(max(0, $list - $period), 2), 'services_net' => round($svcNet, 2), 'addons' => $addonKeys, 'verticals' => $vertKeys, 'svc' => $svc, 'vat_pct' => PUB_VAT];
}

/* ---------- Slanje upita ---------- */
function pub_clean(mixed $v, int $max): string { $s = trim((string)$v); $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? ''; return mb_substr($s, 0, $max); }
function pub_one_line(mixed $v, int $max): string { return trim(preg_replace('/\s+/u', ' ', pub_clean($v, $max)) ?? ''); }

/* Vraća [greške, ref|null]. 'fake' = honeypot (bot) — ništa se ne sprema. */
function pub_submit(array $d): array {
  if ($e = pub_token_check((string)($_POST['t'] ?? ''))) return [[$e], null];
  if (pub_clean($_POST['website'] ?? '', 50) !== '') return [[], 'fake'];
  if (!pub_rate_ok()) return [['Poslano je previše upita s ove adrese. Pokušajte ponovo kasnije ili nas kontaktirajte telefonom.'], null];
  $err = []; $kind = ($_POST['kind'] ?? '') === 'ACCOUNTANT' ? 'ACCOUNTANT' : 'COMPANY';
  $f = [
    'company' => pub_one_line($_POST['company_name'] ?? '', 190), 'contact' => pub_one_line($_POST['contact_name'] ?? '', 160), 'email' => pub_one_line($_POST['email'] ?? '', 190),
    'phone' => pub_one_line($_POST['phone'] ?? '', 40), 'city' => pub_one_line($_POST['city'] ?? '', 120), 'jib' => pub_one_line($_POST['jib'] ?? '', 30), 'pdv' => pub_one_line($_POST['pdv'] ?? '', 30),
    'message' => pub_clean($_POST['message'] ?? '', 4000),
  ];
  $nameLabel = $kind === 'ACCOUNTANT' ? 'Naziv knjigovodstvene agencije / obrta' : 'Naziv firme';
  if ($f['company'] === '') $err[] = $nameLabel . ' je obavezan.';
  if ($f['contact'] === '') $err[] = 'Ime i prezime kontakt osobe je obavezno.';
  if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Unesite ispravnu email adresu.';
  if (!preg_match('/^[0-9+()\/\-\s.]{6,40}$/', $f['phone'])) $err[] = 'Unesite ispravan broj telefona.';
  foreach (['jib' => 'JIB', 'pdv' => 'PDV broj'] as $k => $l) if ($f[$k] !== '' && !preg_match('/^[0-9A-Za-z]{8,15}$/', str_replace(' ', '', $f[$k]))) $err[] = $l . ' nije u ispravnom formatu.';
  if (empty($_POST['consent'])) $err[] = 'Potrebna je saglasnost za obradu podataka radi odgovora na upit.';
  $pref = ($_POST['contact_pref'] ?? '') === 'phone' ? 'phone' : 'email';
  $enum = fn(string $k, array $allowed, string $def) => in_array((string)($_POST[$k] ?? ''), $allowed, true) ? (string)$_POST[$k] : $def;
  $num = fn(string $k, int $max) => max(0, min($max, (int)($_POST[$k] ?? 0)));
  $details = ['contact_pref' => $pref, 'user_agent' => pub_one_line($_SERVER['HTTP_USER_AGENT'] ?? '', 200)];
  $config = []; $calc = null; $pkgCode = null; $cycle = null; $profile = null; $tot = [0.0, 0.0, 0.0, 0.0];
  if ($kind === 'COMPANY') {
    $profile = $enum('profile', ['retail', 'optika', 'moto', 'other'], 'other');
    $details += ['users' => $num('users_count', 10000), 'locations' => $num('locations', 1000), 'pos' => $num('pos_count', 1000), 'fiscal' => $enum('fiscal_device', ['DATECS', 'TRING', 'NONE', 'UNKNOWN'], 'UNKNOWN'),
      'migration' => $enum('migration', ['YES', 'NO', 'UNKNOWN'], 'UNKNOWN'), 'start' => $enum('start_when', ['ASAP', '1M', '3M', 'LATER'], 'LATER'), 'current_software' => pub_one_line($_POST['current_software'] ?? '', 120)];
    $calc = pub_compute($_POST, $d); $err = array_merge($err, $calc['errors']);
    $config = $calc; $pkgCode = $calc['pkg']['code'] ?? null; $cycle = $calc['cycle']; $tot = [$calc['once'], $calc['period'], $calc['net'], $calc['gross']];
  } else {
    $clients = max(1, min(5000, (int)($_POST['clients_count'] ?? 0)));
    $acc = $d['addons']['accountant'] ?? null;
    $details += ['clients' => $clients];
    $config = ['kind' => 'ACCOUNTANT', 'clients' => $clients];
    if ($acc) {
      $pct = (float)$acc['partner_commission_pct']; $bonus = (float)$acc['referral_bonus'];
      $config += ['module_monthly' => (float)$acc['monthly_price'], 'commission_pct' => $pct, 'referral_bonus' => $bonus,
        'est_monthly_commission' => round($clients * (float)$acc['monthly_price'] * $pct / 100, 2), 'est_bonus_total' => round($clients * $bonus, 2)];
    }
  }
  if ($err) return [$err, null];

  inquiry_schema(); $pdo = db(); $ref = null;
  for ($try = 0; $try < 6 && !$ref; $try++) {
    $prefix = 'UP-' . date('Y') . '-'; $last = q('SELECT ref_no FROM inquiries WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%'])->fetchColumn();
    $cand = $prefix . str_pad((string)(($last ? (int)substr((string)$last, -4) : 0) + 1 + $try), 4, '0', STR_PAD_LEFT);
    try {
      q('INSERT INTO inquiries(ref_no,kind,status,company_name,jib,pdv,city,contact_name,email,phone,profile,package_code,billing_cycle,total_once,total_period,total_net,total_gross,message,details_json,config_json,consent_at,ip_hash,created_at,updated_at) VALUES(?,?,"NEW",?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())',
        [$cand, $kind, $f['company'], $f['jib'] ?: null, $f['pdv'] ?: null, $f['city'] ?: null, $f['contact'], $f['email'], $f['phone'], $profile, $pkgCode, $cycle, $tot[0], $tot[1], $tot[2], $tot[3], $f['message'] ?: null,
         json_encode($details, JSON_UNESCAPED_UNICODE), json_encode($config, JSON_UNESCAPED_UNICODE), hash('sha256', client_ip())]);
      $ref = $cand;
    } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
  }
  if (!$ref) return [['Upit trenutno nije moguće spremiti. Pokušajte ponovo ili nas kontaktirajte telefonom.'], null];
  $id = (int)$pdo->lastInsertId(); pub_rate_hit();
  audit('INQUIRY_NEW', 'inquiry', $id, ['ref' => $ref, 'kind' => $kind]);
  $sent = pub_notify($id, $ref, $kind, $f, $calc, $details);
  q('UPDATE inquiries SET notified=? WHERE id=?', [$sent ? 1 : 0, $id]);
  return [[], $ref];
}

/* Obavijest emailom (ako mail() radi na serveru). Upit je u svakom slučaju spremljen u Control Center. */
function pub_notify(int $id, string $ref, string $kind, array $f, ?array $calc, array $details): bool {
  try {
    $to = local_cfg()['notify_email'] ?? null;
    $rcpt = is_string($to) && $to !== '' ? array_map('trim', explode(',', $to)) : array_column(q("SELECT email FROM users WHERE active=1 AND role IN ('SUPERADMIN','ADMIN')")->fetchAll(), 'email');
    $rcpt = array_values(array_filter($rcpt, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', (string)$x)));
    if (!$rcpt) return false;
    $b = ($kind === 'ACCOUNTANT' ? "Novi upit knjigovođe" : "Novi upit za MMC ERP") . " — $ref\n\n" .
      "Firma: {$f['company']}\nKontakt: {$f['contact']}\nEmail: {$f['email']}\nTelefon: {$f['phone']}\nMjesto: {$f['city']}\n";
    if ($calc && $calc['pkg']) $b .= "\nKonfiguracija: paket {$calc['pkg']['name']}, {$calc['cycle_label']}\nUkupno bez PDV: " . money_bam($calc['net']) . " (sa PDV: " . money_bam($calc['gross']) . ")\n";
    if ($f['message'] !== '') $b .= "\nPitanja / napomena:\n{$f['message']}\n";
    $b .= "\nOtvori u Control Centru: " . pub_site_url() . "/?page=inquiry&id=$id\n";
    $h = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: MMC ERP <noreply@" . (parse_url(pub_site_url(), PHP_URL_HOST) ?: 'erp.mmc.ba') . ">\r\nReply-To: {$f['email']}\r\n";
    $subject = '=?UTF-8?B?' . base64_encode("Novi upit $ref — " . mb_substr($f['company'], 0, 80)) . '?=';
    return (bool)@mail(implode(',', $rcpt), $subject, $b, $h);
  } catch (Throwable $e) { error_log('CC inquiry notify: ' . $e->getMessage()); return false; }
}

/* ---------- Ulazna točka: GET prikaz / POST slanje ---------- */
function pub_entry(): never {
  inquiry_schema();
  $d = pub_data(); $errors = []; $done = null;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { [$errors, $ref] = pub_submit($d); } catch (Throwable $e) { error_log('CC inquiry: ' . $e->getMessage()); $errors = ['Došlo je do greške na serveru. Pokušajte ponovo ili nas kontaktirajte telefonom.']; $ref = null; }
    if ($ref) { header('Location: ' . pub_url() . (str_contains(pub_url(), '?') ? '&' : '?') . 'hvala=' . rawurlencode($ref === 'fake' ? 'UP-' . date('Y') . '-0000' : $ref)); exit; }
    http_response_code(422);
  }
  if (isset($_GET['hvala']) && preg_match('/^UP-\d{4}-\d{4}$/', (string)$_GET['hvala'])) $done = (string)$_GET['hvala'];
  header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('Cache-Control: no-cache');
  require __DIR__ . '/public_page.php';
  pub_render($d, $errors, $done);
  exit;
}

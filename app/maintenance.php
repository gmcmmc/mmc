<?php
/*
 * MMC ERP Control Center 1.7.1 — održavanje:
 *  1) samoizlječenje roota: brisanje ERP datoteka koje su greškom raspakirane u root erp.mmc.ba,
 *  2) upozorenje ako se ERP datoteke ponovno pojave,
 *  3) čišćenje starih/zamijenjenih update paketa (dry-run pa potvrda).
 * Nikad ne dira storage/, config.local.php niti datoteke Control Centra.
 */
declare(strict_types=1);

function cc_root(): string { return dirname(__DIR__); }

/* Točan popis stranih (ERP) datoteka pronađenih u produkcijskom rootu erp.mmc.ba (backup 30.09.2026).
 * config.php, public/style.css, mmc-update.json, app/schema.sql, app/update_api.php i docs/README.txt NISU ovdje:
 * to su datoteke CC-a (paket 1.7.1 ih vraća). */
function cc_foreign_files(): array {
  return [
    'app/autocomplete.php', 'app/bootstrap.php', 'app/chart_accounts_seed.php', 'app/dms.php', 'app/fiscal.php',
    'app/hikvision.php', 'app/init.php', 'app/migration.php', 'app/output_reports.php', 'app/pos.php', 'app/profile.php',
    'app/pwa.php', 'app/retail_extras.php', 'app/retail_reports.php', 'app/schema.mysql.sql', 'app/schema.sqlite.sql',
    'app/schema_v2.mysql.sql', 'app/ui.php', 'app/updater.php', 'app/v2.php', 'app/v3.php',
    'docs/A4_DOCUMENT_STANDARD.md', 'docs/FISCAL_TRING.md', 'docs/FISKALIZACIJA_DATECS_V3.6.0.md',
    'docs/README_v5.12.0_JEZGRA.txt', 'docs/README_v5.12.1_NADOGRADNJA.txt', 'docs/README_v5.12.2_MOTO.txt',
    'docs/SECURITY.md', 'docs/mmc-cleanup.json',
    'public/a4-doc.css', 'public/assets/login-moto.svg', 'public/assets/login-optika.svg', 'public/assets/login-retail.svg',
    'public/datecs-bridge.php', 'public/hikvision-bridge.php',
    'public/icons/app-192.png', 'public/icons/app-512.png', 'public/icons/app-maskable-512.png', 'public/icons/app.ico',
    'public/icons/apple-touch-icon.png', 'public/icons/favicon-32.png', 'public/icons/favicon-64.png',
    'public/index.php', 'public/mikro-offline.js', 'public/mmc-agent.php', 'public/offline-pos.html', 'public/offline-pos.js',
    'public/pos-offline-base.css', 'public/pos.css', 'public/sw.js', 'public/theme-moto.css', 'public/theme-optika.css',
  ];
}
/* Direktoriji koje CC nema; brišu se samo ako su nakon čišćenja prazni. */
function cc_foreign_dirs(): array { return ['public/icons', 'public/assets']; }
/* Datoteke CC-a — zaštitna provjera da popis stranih datoteka nikad ne pogodi CC. */
function cc_protected_files(): array {
  return ['index.php', 'install.php', 'config.php', 'config.local.php', 'mmc-update.json', '.htaccess', '.htaccess.off', '.php-ini', '.php-version',
    'app/core.php', 'app/agent_client.php', 'app/update_api.php', 'app/maintenance.php', 'app/schema.sql', 'app/.htaccess',
    'docs/README.txt', 'public/style.css', 'public/mmc-logo.png', 'public/mmc-erp-login-bg.png', 'public/mmc-erp-v104-hero.png',
    'public/mmc-erp-v112-clean-hero.png', 'public/mmc-erp-v112-fullscene.png', 'public/mmc-erp-v112-left-artwork.jpg'];
}

function cc_foreign_present(): array {
  $root = cc_root(); $out = [];
  foreach (cc_foreign_files() as $rel) if (is_file($root . '/' . $rel) || is_link($root . '/' . $rel)) $out[] = $rel;
  return $out;
}

/* Znakovi da je u root CC-a raspakiran ERP paket (za upozorenje). */
function cc_root_problems(): array {
  $p = [];
  $foreign = cc_foreign_present();
  if ($foreign) $p[] = 'U rootu Control Centra ima ' . count($foreign) . ' ERP datoteka (npr. ' . implode(', ', array_slice($foreign, 0, 3)) . ').';
  if (cc_config_overwritten()) $p[] = 'config.php je prepisan ERP verzijom — ponovno raspakiraj paket Control Centra ' . '1.7.1 ili noviji.';
  $mf = cc_root() . '/mmc-update.json';
  if (is_file($mf)) { $m = json_decode((string)@file_get_contents($mf), true); if (is_array($m) && ($m['target_app'] ?? '') !== 'control-center') $p[] = 'mmc-update.json u rootu je ERP-ov, ne od Control Centra.'; }
  return $p;
}

function cc_path_inside_root(string $rel): ?string {
  $rel = str_replace('\\', '/', $rel);
  if ($rel === '' || str_starts_with($rel, '/') || str_contains($rel, '..') || str_starts_with($rel, 'storage/')) return null;
  if (in_array($rel, cc_protected_files(), true)) return null;
  $root = realpath(cc_root()); if ($root === false) return null;
  $dir = realpath(dirname($root . '/' . $rel));
  if ($dir === false || ($dir !== $root && !str_starts_with($dir . '/', $root . '/'))) return null;
  if (str_starts_with($dir . '/', $root . '/storage/')) return null;
  return $dir . '/' . basename($rel);
}

/* Briše strane ERP datoteke. Idempotentno: kad ih nema, ne radi ništa. */
function cc_root_cleanup(string $trigger): array {
  $res = ['trigger' => $trigger, 'deleted' => [], 'failed' => [], 'dirs' => [], 'dirs_kept' => []];
  foreach (cc_foreign_present() as $rel) {
    $abs = cc_path_inside_root($rel);
    if ($abs === null) { $res['failed'][] = $rel; continue; }
    if (@unlink($abs)) $res['deleted'][] = $rel; else $res['failed'][] = $rel;
  }
  foreach (cc_foreign_dirs() as $rel) {
    $abs = cc_root() . '/' . $rel;
    if (!is_dir($abs) || is_link($abs)) continue;
    $left = array_values(array_diff(scandir($abs) ?: [], ['.', '..']));
    if (!$left && @rmdir($abs)) $res['dirs'][] = $rel; else $res['dirs_kept'][] = $rel;
  }
  if (function_exists('opcache_reset')) @opcache_reset();
  $action = $res['failed'] ? 'CC_ROOT_CLEANUP_PARTIAL' : 'CC_ROOT_CLEANUP';
  audit($action, 'system', null, ['trigger' => $trigger, 'version' => cc_version(), 'deleted' => $res['deleted'], 'failed' => $res['failed'], 'dirs_removed' => $res['dirs'], 'dirs_kept' => $res['dirs_kept']]);
  return $res;
}

/* Jednokratno automatsko čišćenje kad admin otvori nadzornu ploču ili Sistem.
 * Oznaka "odrađeno" je zapis CC_ROOT_CLEANUP u audit logu (ne piše se ništa u storage). */
function cc_root_cleanup_auto(): ?array {
  if (!is_admin()) return null;
  try {
    if (q("SELECT 1 FROM audit_log WHERE action='CC_ROOT_CLEANUP' LIMIT 1")->fetchColumn()) return null;
  } catch (Throwable $e) { return null; }
  return cc_root_cleanup('auto');
}

function cc_cleanup_notice(?array $r): string {
  if (!$r) return '';
  if (($r['trigger'] ?? '') === 'auto' && !$r['deleted'] && !$r['failed'] && !$r['dirs']) return ''; // ništa za očistiti — bez obavijesti
  $n = count($r['deleted']);
  $h = '<div class="alert ' . ($r['failed'] ? 'err' : 'ok') . '"><b>Čišćenje roota Control Centra:</b> ';
  $h .= $n ? 'uklonjeno ' . $n . ' ERP datoteka koje ne pripadaju Control Centru' : 'nije pronađena nijedna ERP datoteka';
  if ($r['dirs']) $h .= ' i ' . count($r['dirs']) . ' praznih mapa (' . e(implode(', ', $r['dirs'])) . ')';
  $h .= '.';
  if ($r['failed']) $h .= ' Nije uspjelo obrisati ' . count($r['failed']) . ' datoteka (provjeri prava): ' . e(implode(', ', array_slice($r['failed'], 0, 8))) . '.';
  if ($n) $h .= ' <details><summary>Popis</summary><small>' . e(implode(', ', $r['deleted'])) . '</small></details>';
  return $h . ' Zapis je u Auditu.</div>';
}

function cc_root_warning_banner(): string {
  if (!is_admin()) return '';
  $p = cc_root_problems(); if (!$p) return '';
  $h = '<div class="alert err"><b>Upozorenje: u rootu erp.mmc.ba su ERP datoteke.</b> ERP paketi (MIKRO, Optika, AutoDMS) se <b>učitavaju u Update Center</b> (Update Center › Dodaj novi update ZIP) i odatle šalju firmama — nikad se ne raspakiravaju u root Control Centra, jer prepisuju njegove datoteke (config.php, style.css) i izlažu ERP skripte na ovoj domeni.<ul>';
  foreach ($p as $x) $h .= '<li>' . e($x) . '</li>';
  $h .= '</ul>';
  if (cc_foreign_present()) $h .= '<form method="post" class="inline-form" onsubmit="return confirm(\'Obrisati poznate ERP datoteke iz roota Control Centra? storage/ i config.local.php se ne diraju.\');"><input type="hidden" name="csrf" value="' . e(csrf()) . '"><input type="hidden" name="action" value="cc_root_cleanup"><button class="btn small">Ukloni ERP datoteke iz roota</button></form>';
  return $h . '</div>';
}

/* ---------- Čišćenje starih update paketa ---------- */

function pkg_vnum(string $v): string { return (string)preg_replace('/[^0-9.].*$/', '', $v); }
function pkg_cmp(array $a, array $b): int { // silazno: najnovija verzija prva
  return version_compare(pkg_vnum((string)$b['version']), pkg_vnum((string)$a['version']))
    ?: version_compare((string)$b['version'], (string)$a['version'])
    ?: ((int)$b['id'] <=> (int)$a['id']);
}
function pkg_group_key(array $p): string {
  $m = package_manifest($p);
  $target = strtolower(trim((string)($p['target_app'] ?: ($m['target_app'] ?? 'all'))));
  if (!in_array($target, ['all', 'core', 'common', ''], true)) $target = profile_alias($target);
  if (in_array($target, ['core', 'common', ''], true)) $target = 'all';
  $slugs = array_map(fn($x) => strtolower(trim((string)$x)), (array)($m['tenant_slugs'] ?? [])); sort($slugs);
  return $target . '|' . package_scope($p) . '|' . implode(',', $slugs);
}

function pkg_cleanup_plan(): array {
  require_once __DIR__ . '/update_api.php';
  $rows = q('SELECT * FROM update_packages ORDER BY id')->fetchAll();
  $keep = []; // id => razlog
  // 1) najnovija verzija po cilju/opsegu (među nearhiviranim)
  $groups = [];
  foreach ($rows as $p) if (empty($p['archived_at'])) $groups[pkg_group_key($p)][] = $p;
  foreach ($groups as $g => $list) { usort($list, 'pkg_cmp'); $keep[(int)$list[0]['id']] = 'najnovija za ' . $g; }
  // 2) rollout koji nije završen
  foreach (q("SELECT DISTINCT package_id FROM update_rollouts WHERE finished_at IS NULL OR status='RUNNING'")->fetchAll() as $r) $keep[(int)$r['package_id']] ??= 'rollout u tijeku';
  // 3) STABILNA koju update_api trenutno nudi nekoj firmi
  foreach (q("SELECT * FROM tenants WHERE agent_secret IS NOT NULL AND agent_secret<>'' AND UPPER(status)='ACTIVE'")->fetchAll() as $t)
    foreach (upd_api_packages($t) as $p) $keep[(int)$p['id']] ??= 'STABILNA, nudi se firmi ' . $t['slug'];
  $usedRollout = array_fill_keys(array_map('intval', array_column(q('SELECT DISTINCT package_id FROM update_rollouts')->fetchAll(), 'package_id')), true);
  $usedVersions = array_fill_keys(array_column(q('SELECT DISTINCT to_version FROM deployments WHERE to_version IS NOT NULL')->fetchAll(), 'to_version'), true);
  $keptFiles = [];
  foreach ($rows as $p) if (isset($keep[(int)$p['id']])) $keptFiles[basename((string)$p['package_file'])] = true;
  $items = []; $kept = [];
  foreach ($rows as $p) {
    $id = (int)$p['id']; $file = basename((string)$p['package_file']); $path = package_dir() . '/' . $file;
    $has = $file !== '' && is_file($path) && !is_link($path);
    if (isset($keep[$id])) { $kept[] = ['id' => $id, 'version' => $p['version'], 'name' => $p['name'], 'reason' => $keep[$id], 'size' => $has ? filesize($path) : 0]; continue; }
    $reason = !empty($p['archived_at']) ? 'arhiviran' : 'zamijenjen novijim paketom';
    $used = isset($usedRollout[$id]) || isset($usedVersions[(string)$p['version']]);
    if (!$has && $used && !empty($p['archived_at'])) continue; // već očišćen, zapis se čuva radi povijesti
    $items[] = ['id' => $id, 'version' => (string)$p['version'], 'name' => (string)$p['name'], 'file' => $file, 'size' => $has ? (int)filesize($path) : 0,
      'has_file' => $has && !isset($keptFiles[$file]), 'reason' => $reason,
      'mode' => $used ? 'archive' : 'delete']; // archive = briše se ZIP, zapis ostaje arhiviran (povijest deploymenta/rollouta)
  }
  // 4) ZIP datoteke bez zapisa u bazi (starije od 1 sata, da ne diramo upload u tijeku)
  $known = array_fill_keys(array_map(fn($p) => basename((string)$p['package_file']), $rows), true); $orphans = [];
  foreach (scandir(package_dir()) ?: [] as $f) {
    if (!preg_match('/^[A-Za-z0-9._-]+\.zip$/', $f) || isset($known[$f])) continue;
    $path = package_dir() . '/' . $f; if (!is_file($path) || is_link($path) || filemtime($path) > time() - 3600) continue;
    $orphans[] = ['file' => $f, 'size' => (int)filesize($path)];
  }
  $bytes = array_sum(array_map(fn($x) => $x['has_file'] ? $x['size'] : 0, $items)) + array_sum(array_column($orphans, 'size'));
  $hash = hash('sha256', json_encode([$items, $orphans]));
  return ['items' => $items, 'orphans' => $orphans, 'kept' => $kept, 'bytes' => $bytes, 'hash' => $hash];
}

function pkg_cleanup_execute(string $hash, bool $withOrphans): array {
  $plan = pkg_cleanup_plan();
  if (!hash_equals($plan['hash'], $hash)) throw new RuntimeException('Popis paketa se promijenio u međuvremenu. Pregledaj ga ponovno pa potvrdi.');
  $dir = package_dir(); $done = ['deleted_rows' => [], 'archived_rows' => [], 'files' => [], 'bytes' => 0, 'failed' => []];
  foreach ($plan['items'] as $it) {
    if ($it['has_file']) {
      $path = $dir . '/' . basename($it['file']);
      if (@unlink($path)) { $done['files'][] = $it['file']; $done['bytes'] += $it['size']; } else { $done['failed'][] = $it['file']; continue; }
    }
    if ($it['mode'] === 'delete') { q('DELETE FROM update_packages WHERE id=?', [$it['id']]); $done['deleted_rows'][] = $it['version'] . ' #' . $it['id']; }
    else { q('UPDATE update_packages SET archived_at=COALESCE(archived_at,NOW()) WHERE id=?', [$it['id']]); $done['archived_rows'][] = $it['version'] . ' #' . $it['id']; }
  }
  if ($withOrphans) foreach ($plan['orphans'] as $o) {
    if (@unlink($dir . '/' . basename($o['file']))) { $done['files'][] = $o['file']; $done['bytes'] += $o['size']; } else $done['failed'][] = $o['file'];
  }
  audit('PACKAGE_CLEANUP', 'update_package', null, $done + ['orphans_included' => $withOrphans]);
  return $done;
}

function fmt_bytes(int $b): string { return $b >= 1048576 ? number_format($b / 1048576, 2, ',', '.') . ' MB' : number_format($b / 1024, 1, ',', '.') . ' KB'; }

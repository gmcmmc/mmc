<?php
/*
 * MMC ERP Control Center 1.7.0+ — API za nadogradnju koju pokreće sama firma (ERP › Sistem › Nadogradnja).
 * Firma se prijavljuje istim tajnim ključem kao MMC Agent (mmc-agent.config.php ↔ tenants.agent_secret):
 *   kid = prvih 16 znakova sha256(secret), sig = HMAC-SHA256(secret, "<akcija>|<ts>|<paket>").
 * Firmi se nudi samo paket koji je u Update Centru označen kao STABILNA i prolazi package_target_ok().
 */
declare(strict_types=1);

function upd_api_out(array $x, int $code = 200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

function upd_api_tenant(string $action, string $extra = ''): array {
  $kid = strtolower(preg_replace('/[^a-f0-9]/i', '', (string)($_GET['kid'] ?? '')));
  $ts = (int)($_GET['ts'] ?? 0); $sig = strtolower((string)($_GET['sig'] ?? ''));
  if (strlen($kid) !== 16 || $sig === '') upd_api_out(['error' => 'Firma nije povezana s Control Centrom (nedostaje ključ).'], 401);
  if (abs(time() - $ts) > 900) upd_api_out(['error' => 'Vrijeme na serveru firme se ne slaže s Control Centrom.'], 401);
  foreach (q("SELECT * FROM tenants WHERE agent_secret IS NOT NULL AND agent_secret<>''")->fetchAll() as $t) {
    $secret = (string)$t['agent_secret'];
    if (substr(hash('sha256', $secret), 0, 16) !== $kid) continue;
    if (!hash_equals(hash_hmac('sha256', $action . '|' . $ts . '|' . $extra, $secret), $sig)) break;
    if (strtoupper((string)($t['status'] ?? 'ACTIVE')) !== 'ACTIVE') upd_api_out(['error' => 'Firma nije aktivna u Control Centru.'], 403);
    return $t;
  }
  upd_api_out(['error' => 'Ključ firme nije prepoznat u Control Centru.'], 403);
}

function upd_api_vnum(string $v): string { return preg_replace('/[^0-9.].*$/', '', $v); }

function upd_api_packages(array $t): array {
  $rows = q("SELECT * FROM update_packages WHERE archived_at IS NULL AND UPPER(release_channel)='STABLE' ORDER BY id DESC")->fetchAll();
  $rows = array_values(array_filter($rows, fn($p) => package_target_ok($t, $p) && is_file(package_path($p))));
  usort($rows, fn($a, $b) => version_compare(upd_api_vnum((string)$b['version']), upd_api_vnum((string)$a['version'])) ?: ((int)$b['id'] <=> (int)$a['id']));
  return $rows;
}

function update_api_handle(): void {
  agent_schema();
  $api = (string)$_GET['api'];
  if ($api === 'channel') {
    $t = upd_api_tenant('channel');
    $ver = substr(preg_replace('/[^0-9A-Za-z.\-]/', '', (string)($_GET['version'] ?? '')), 0, 50);
    if ($ver !== '' && $ver !== (string)$t['installed_version']) {
      q('INSERT INTO deployments(tenant_id,from_version,to_version,status,message,started_at,finished_at) VALUES(?,?,?,?,?,NOW(),NOW())', [(int)$t['id'], (string)$t['installed_version'], $ver, 'SUCCESS', 'Verzija prijavljena iz ERP-a (nadogradnja pokrenuta u firmi ili ručno).']);
      audit('TENANT_VERSION_REPORTED', 'tenant', (int)$t['id'], ['from' => $t['installed_version'], 'to' => $ver]);
    }
    q("UPDATE tenants SET installed_version=IF(?<>'',?,installed_version),last_health_at=NOW(),last_health_status=?,updated_at=NOW() WHERE id=?", [$ver, $ver, 'ONLINE', (int)$t['id']]);
    $t['installed_version'] = $ver !== '' ? $ver : $t['installed_version'];
    $pk = upd_api_packages($t);
    $pub = fn($p) => ['id' => (int)$p['id'], 'version' => (string)$p['version'], 'name' => (string)$p['name'], 'notes' => (string)($p['notes'] ?: $p['short_description']), 'sha256' => (string)$p['checksum'], 'size' => (int)$p['size_bytes'], 'published_at' => (string)$p['created_at']];
    upd_api_out(['latest' => $pk ? $pub($pk[0]) : null, 'history' => array_map($pub, array_slice($pk, 0, 10)), 'tenant' => (string)$t['slug']]);
  }
  if ($api === 'download') {
    $id = (int)($_GET['pkg'] ?? 0); $t = upd_api_tenant('download', (string)$id);
    foreach (upd_api_packages($t) as $p) if ((int)$p['id'] === $id) {
      $f = package_path($p); audit('TENANT_PACKAGE_DOWNLOAD', 'tenant', (int)$t['id'], ['package' => $p['version']]);
      header('Content-Type: application/zip'); header('Content-Length: ' . filesize($f)); header('Content-Disposition: attachment; filename="mmc-erp-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$p['version']) . '.zip"'); header('Cache-Control: no-store');
      readfile($f); exit;
    }
    upd_api_out(['error' => 'Paket nije dostupan za ovu firmu.'], 404);
  }
  upd_api_out(['error' => 'Nepoznat API poziv.'], 404);
}

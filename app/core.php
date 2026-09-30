<?php
declare(strict_types=1);
function cfg():array{static $c;return $c??=require __DIR__.'/../config.php';}
date_default_timezone_set((string)(cfg()['timezone']??'Europe/Sarajevo'));
/* v1.7.1: verzija CC-a dolazi samo iz config.php. Ako je config.php prepisan ERP-ovim (nema 'version'), vraća '?'. */
function cc_version():string{$v=cfg()['version']??null;return is_string($v)&&$v!==''?$v:'?';}
function cc_config_overwritten():bool{$c=cfg();return !isset($c['version'])||isset($c['app_version'])||isset($c['db_dsn']);}
function local_cfg():array{ $f=__DIR__.'/../config.local.php'; return is_file($f)?require $f:[]; }
function db():PDO{static $p;if($p)return $p;$c=local_cfg();if(!$c)throw new RuntimeException('NOT_INSTALLED');$p=new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function installed():bool{return is_file(__DIR__.'/../config.local.php');}
/* v1.7.1: zaključavanje instalera — ostaje i ako config.local.php nestane, pa se install.php ne može ponovno pokrenuti. */
function install_lock_file():string{return __DIR__.'/../storage/installed.lock';}
function install_locked():bool{return is_file(install_lock_file());}
function ensure_install_lock():void{if(installed()&&!install_locked()&&is_dir(dirname(install_lock_file())))@file_put_contents(install_lock_file(),date('c')."\n",LOCK_EX);}
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function start_session():void{if(session_status()!==PHP_SESSION_ACTIVE){session_name('mmc_erp_platform');$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);session_start();}}
function is_admin():bool{return in_array(strtoupper((string)(user()['role']??'')),['SUPERADMIN','ADMIN'],true);}
/* v1.8.0: sesija istječe nakon neaktivnosti (60 min) i najkasnije nakon 12 h od prijave. */
const CC_IDLE_TIMEOUT=3600;const CC_MAX_SESSION=43200;
function user():?array{
 start_session();$u=$_SESSION['user']??null;if(!$u)return null;$now=time();
 $last=(int)($_SESSION['last_seen']??$now);$since=(int)($_SESSION['login_at']??$now);
 if($now-$last>CC_IDLE_TIMEOUT||$now-$since>CC_MAX_SESSION){logout_session('expired');return null;}
 $_SESSION['last_seen']=$now;return $u;
}
function require_login():void{if(!user()){header('Location: ?page=login');exit;}}
function login_session(array $u):void{start_session();session_regenerate_id(true);$_SESSION=['user'=>['id'=>(int)$u['id'],'name'=>(string)$u['name'],'email'=>(string)$u['email'],'role'=>(string)$u['role']],'login_at'=>time(),'last_seen'=>time()];}
/* Uništava sesiju; $notice ('logout'/'expired') se prenosi u novu, praznu sesiju za poruku na prijavi. */
function logout_session(string $notice=''):void{
 start_session();$_SESSION=[];
 if(ini_get('session.use_cookies')){$cp=session_get_cookie_params();setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$cp['path'],'domain'=>$cp['domain'],'secure'=>$cp['secure'],'httponly'=>$cp['httponly'],'samesite'=>$cp['samesite']??'Lax']);}
 session_destroy();
 if($notice!==''&&!headers_sent()){session_id(session_create_id());start_session();$_SESSION['auth_notice']=$notice;}
}
function user_initials(string $name):string{$p=preg_split('/\s+/u',trim($name))?:[];$i='';foreach(array_slice($p,0,2) as $w)$i.=mb_strtoupper(mb_substr($w,0,1));return $i!==''?$i:'A';}
function role_label(string $r):string{return match(strtoupper($r)){'SUPERADMIN'=>'Glavni administrator','ADMIN'=>'Administrator',default=>ucfirst(strtolower($r))};}
/* v1.8.0: zaštita prijave — nakon 5 neuspjelih pokušaja s iste IP adrese ili za isti email prijava je blokirana 15 min. */
const CC_LOGIN_MAX_FAILS=5;const CC_LOGIN_WINDOW=900;
function login_throttle_file():string{return __DIR__.'/../storage/login-throttle.json';}
function login_throttle_keys(string $email):array{$ip=(string)($_SERVER['REMOTE_ADDR']??'');return ['ip:'.hash('sha256',$ip),'em:'.hash('sha256',strtolower(trim($email)))];}
function login_throttle_load():array{$f=login_throttle_file();$d=is_file($f)?json_decode((string)@file_get_contents($f),true):[];$d=is_array($d)?$d:[];$now=time();return array_filter($d,fn($x)=>is_array($x)&&$now-(int)($x['t']??0)<CC_LOGIN_WINDOW);}
function login_throttle_save(array $d):void{$dir=dirname(login_throttle_file());if(is_dir($dir)&&is_writable($dir))@file_put_contents(login_throttle_file(),json_encode($d),LOCK_EX);}
/* Vraća broj sekundi do otključavanja (0 = prijava dozvoljena). */
function login_locked_for(string $email):int{$d=login_throttle_load();$max=0;foreach(login_throttle_keys($email) as $k)if(($d[$k]['n']??0)>=CC_LOGIN_MAX_FAILS)$max=max($max,CC_LOGIN_WINDOW-(time()-(int)$d[$k]['t']));return max(0,$max);}
function login_register_fail(string $email):void{$d=login_throttle_load();foreach(login_throttle_keys($email) as $k){$n=(int)($d[$k]['n']??0)+1;$d[$k]=['n'=>$n,'t'=>$n>=CC_LOGIN_MAX_FAILS?time():(int)($d[$k]['t']??time())];}login_throttle_save($d);}
function login_clear_fails(string $email):void{$d=login_throttle_load();foreach(login_throttle_keys($email) as $k)unset($d[$k]);login_throttle_save($d);}
function csrf():string{start_session();return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function check_csrf():void{start_session();if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??''))throw new RuntimeException('Neispravan sigurnosni token.');}
function q(string $sql,array $p=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($p);return $s;}
function audit(string $action,string $entity='',?int $id=null,array $meta=[]):void{try{q('INSERT INTO audit_log(user_email,action,entity,entity_id,meta_json,created_at) VALUES(?,?,?,?,?,NOW())',[user()['email']??'system',$action,$entity,$id,json_encode($meta,JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){}}
function module_definitions():array{return [
 'core'=>['name'=>'Core','group'=>'Osnova','description'=>'Osnovni sustav, korisnici, prava, postavke i audit.','required'=>true,'depends'=>[]],
 'partners'=>['name'=>'Partneri','group'=>'Osnova','description'=>'Kupci, dobavljači, kontakti i poslovni partneri.','depends'=>['core']],
 'inventory'=>['name'=>'Skladište','group'=>'Roba i logistika','description'=>'Artikli, zalihe, skladišta i osnovno kretanje robe.','depends'=>['core']],
 'purchasing'=>['name'=>'Nabava / Ulaz','group'=>'Roba i logistika','description'=>'Narudžbe dobavljačima, prijem, kalkulacije i ulazni dokumenti.','depends'=>['partners','inventory']],
 'sales'=>['name'=>'Izlaz / Prodaja','group'=>'Prodaja','description'=>'Ponude, otpremnice, računi i prodajni dokumenti.','depends'=>['partners','inventory']],
 'pos'=>['name'=>'Maloprodaja / POS','group'=>'Prodaja','description'=>'Brza maloprodaja, blagajna i fiskalni račun.','depends'=>['sales','inventory']],
 'cash'=>['name'=>'Blagajna','group'=>'Financije','description'=>'Blagajničko poslovanje, uplate, isplate i dnevni promet.','depends'=>['core']],
 'finance'=>['name'=>'Financije','group'=>'Financije','description'=>'Financijski pregledi, obveze, potraživanja i kontrola poslovanja.','depends'=>['partners']],
 'vat'=>['name'=>'PDV knjige','group'=>'Financije','description'=>'KUF/KIF i PDV evidencije.','depends'=>['purchasing','sales','finance']],
 'uio'=>['name'=>'UIO eKUF/eKIF','group'=>'Financije','description'=>'Priprema i kontrola UIO elektronskih evidencija.','depends'=>['vat']],
 'mobile'=>['name'=>'Mobile Workspace','group'=>'Operativa','description'=>'Mobilni rad po ulozi: skeniranje, prijem, izdavanje, inventura i brze akcije.','depends'=>['core'],'commercial'=>true],
 'warehouse'=>['name'=>'Advanced Warehouse','group'=>'Operativa','description'=>'Barcode/QR, inbound kontrola, put-away, picking, bin lokacije i mobilna inventura.','depends'=>['inventory','purchasing','sales'],'commercial'=>true],
 'analytics'=>['name'=>'Management / BI','group'=>'Operativa','description'=>'KPI dashboardi, trendovi, upozorenja i management pregled poslovanja.','depends'=>['core'],'commercial'=>true],
 'accountant'=>['name'=>'Centar knjigovođe','group'=>'Suradnja','description'=>'Portal knjigovođe, KUF/KIF/PDV kontrola, dokumenti, korekcije i partner program.','depends'=>['finance','vat'],'commercial'=>true],
 'service'=>['name'=>'Servis','group'=>'Vertikale','description'=>'Servisni nalozi, uređaji, dijagnostika, dijelovi i statusi popravka.','depends'=>['partners','inventory']],
 'dms'=>['name'=>'Moto DMS','group'=>'Vertikale','description'=>'Prodaja i evidencija vozila, moto specifičnosti i DMS funkcije.','depends'=>['partners','sales','service']],
 'optics'=>['name'=>'Optika','group'=>'Vertikale','description'=>'Optičarski radni tok, recepti, okviri, leće i narudžbe.','depends'=>['partners','inventory','sales']]
];}
function modules_catalog():array{$out=[];foreach(module_definitions() as $k=>$d)$out[$k]=$d['name'];return $out;}
function module_presets():array{return [
 'START'=>['core','partners','inventory','sales'],
 'BUSINESS'=>['core','partners','inventory','purchasing','sales','pos','cash','finance','vat','uio'],
 'PRO'=>['core','partners','inventory','purchasing','sales','pos','cash','finance','vat','uio','mobile','warehouse','analytics'],
 'MOTO'=>['core','partners','inventory','purchasing','sales','pos','cash','finance','vat','uio','mobile','analytics','service','dms'],
 'OPTIKA'=>['core','partners','inventory','purchasing','sales','pos','cash','finance','vat','uio','mobile','analytics','optics']
];}
function resolve_module_dependencies(array $keys):array{$defs=module_definitions();$set=array_fill_keys($keys,true);$set['core']=true;$changed=true;while($changed){$changed=false;foreach(array_keys($set) as $k){foreach((array)($defs[$k]['depends']??[]) as $d){if(isset($defs[$d])&&!isset($set[$d])){$set[$d]=true;$changed=true;}}}}return array_keys($set);} 
function module_grouped():array{$g=[];foreach(module_definitions() as $k=>$d)$g[$d['group']][$k]=$d;return $g;}
function tenant_modules(int $id):array{return array_column(q('SELECT module_key FROM tenant_modules WHERE tenant_id=? AND enabled=1',[$id])->fetchAll(),'module_key');}

function tenant_profile_modules(string $profile):array{
  return match(profile_alias($profile)){
    'moto'=>module_presets()['MOTO'],
    'optika'=>module_presets()['OPTIKA'],
    'retail'=>module_presets()['BUSINESS'],
    default=>module_presets()['BUSINESS'],
  };
}
/* v1.7.1: jedan popis profila (djelatnosti) za cijeli CC + sinonimi iz manifesta paketa (AutoDMS/AQMC = moto). */
function tenant_profiles():array{return ['retail'=>'Trgovina / maloprodaja','optika'=>'Optika','moto'=>'Moto / vozila (AutoDMS)'];}
function profile_alias(string $p):string{$p=strtolower(trim($p));return match($p){'autodms','auto-dms','aqmc','dms','motoerp','moto-erp','vozila'=>'moto','optics','optikaerp','optika-erp'=>'optika','mikro','trgovina','maloprodaja'=>'retail',default=>$p};}
function tenant_app_type(array $t):string{
  $p=profile_alias((string)($t['profile']??''));
  if(isset(tenant_profiles()[$p]))return $p;
  $slug=strtolower((string)($t['slug']??''));
  if(str_contains($slug,'optika'))return 'optika';
  foreach(['aqmc','autodms','moto'] as $k)if(str_contains($slug,$k))return 'moto';
  return 'retail';
}
function package_manifest(array $p):array{if(empty($p['manifest_json']))return [];$m=json_decode((string)$p['manifest_json'],true);return is_array($m)?$m:[];}
function package_scope(array $p):string{$m=package_manifest($p);$scope=strtolower(trim((string)($m['package_scope']??($p['package_scope']??''))));if(in_array($scope,['common','profile','tenant'],true))return $scope;$target=strtolower(trim((string)($p['target_app']??($m['target_app']??'all'))));return in_array($target,['all','core','common'],true)?'common':'profile';}
function package_target_ok(array $t,array $p):bool{
 $m=package_manifest($p);$profile=tenant_app_type($t);$target=strtolower(trim((string)($p['target_app']??($m['target_app']??'all'))));
 $target=in_array($target,['all','core','common',''],true)?$target:profile_alias($target);
 $profiles=array_values(array_filter(array_map(fn($x)=>profile_alias((string)$x),(array)($m['compatible_profiles']??[]))));if($profiles&&!in_array($profile,$profiles,true))return false;
 $slugs=array_values(array_filter(array_map(fn($x)=>strtolower(trim((string)$x)),(array)($m['tenant_slugs']??[]))));if($slugs&&!in_array(strtolower((string)($t['slug']??'')),$slugs,true))return false;
 if($target!==''&&!in_array($target,['all','core','common'],true)&&$target!==$profile)return false;
 $from=(array)($m['core_from']??[]);if($from){$current=(string)($t['installed_version']??'');$ok=false;foreach($from as $allowed){$allowed=(string)$allowed;if($allowed==='*'||$allowed===$current||(str_ends_with($allowed,'*')&&str_starts_with($current,rtrim($allowed,'*')))){$ok=true;break;}}if(!$ok)return false;}
 return true;
}

function badge(string $txt,string $kind='ok'):string{return '<span class="badge '.$kind.'">'.e($txt).'</span>';}
/* v1.8.0: jedinstveni prikaz statusa firme i datuma kroz cijeli CC. */
function tenant_statuses():array{return ['ACTIVE'=>'Aktivna','PAUSED'=>'Pauzirana','MAINTENANCE'=>'Održavanje'];}
function tenant_status_badge(string $s):string{$s=strtoupper($s);return badge(tenant_statuses()[$s]??$s,$s==='ACTIVE'?'ok':'warn');}
function tenant_stage_label(string $s):string{return match(strtoupper($s)){'ACTIVE'=>'PRODUKCIJSKA INSTANCA','PAUSED'=>'INSTANCA PAUZIRANA','MAINTENANCE'=>'INSTANCA U ODRŽAVANJU',default=>'INSTANCA'};}
function fmt_dt(?string $dt,bool $time=true):string{if(!$dt)return '—';$ts=strtotime($dt);return $ts?date($time?'d.m.Y. H:i':'d.m.Y.',$ts):$dt;}
/* v1.8.0: veza firmi i Core nadogradnji. Brojčani dio verzije (5.12.2-moto -> 5.12.2) se uspoređuje s version_compare. */
function core_vnum(string $v):string{return preg_replace('/[^0-9.].*$/','',trim($v))?:'0';}
function core_version_cmp(string $a,string $b):int{return version_compare(core_vnum($a),core_vnum($b))?:strcmp($a,$b);}
/* Paketi (nearhivirani, kompatibilni s firmom) noviji od instalirane verzije; najnoviji prvi. */
function tenant_update_candidates(array $t,bool $stableOnly=true):array{
 static $rows=null;
 if($rows===null){try{$rows=q('SELECT * FROM update_packages WHERE archived_at IS NULL ORDER BY id DESC')->fetchAll();}catch(Throwable $e){$rows=[];}}
 $cur=(string)($t['installed_version']??'');
 $out=array_values(array_filter($rows,fn($p)=>(!$stableOnly||strtoupper((string)($p['release_channel']??''))==='STABLE')&&package_target_ok($t,$p)&&($cur===''||version_compare(core_vnum((string)$p['version']),core_vnum($cur))>0)));
 usort($out,fn($a,$b)=>core_version_cmp((string)$b['version'],(string)$a['version'])?:((int)$b['id']<=>(int)$a['id']));
 return $out;
}
function tenant_update_state(array $t):array{
 $stable=tenant_update_candidates($t,true);if($stable)return ['state'=>'available','label'=>'Dostupna '.$stable[0]['version'],'package'=>$stable[0]];
 $pilot=tenant_update_candidates($t,false);if($pilot)return ['state'=>'pilot','label'=>'Pilot '.$pilot[0]['version'],'package'=>$pilot[0]];
 return ['state'=>'current','label'=>trim((string)($t['installed_version']??''))!==''?'Ažurno':'Verzija nepoznata','package'=>null];
}

require_once __DIR__.'/agent_client.php';

function commercial_schema():void{
 static $done=false;if($done)return;$done=true;$pdo=db();
 $pdo->exec("CREATE TABLE IF NOT EXISTS price_packages (id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,description TEXT NULL,implementation_price DECIMAL(12,2) NOT NULL DEFAULT 0,monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,semiannual_price DECIMAL(12,2) NOT NULL DEFAULT 0,annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,support_minutes INT NOT NULL DEFAULT 0,active TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS service_catalog (id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(50) NOT NULL UNIQUE,name VARCHAR(190) NOT NULL,description TEXT NULL,unit VARCHAR(30) NOT NULL DEFAULT 'sat',price DECIMAL(12,2) NOT NULL DEFAULT 0,active TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS offers (id INT AUTO_INCREMENT PRIMARY KEY,offer_no VARCHAR(50) NOT NULL UNIQUE,tenant_id INT NULL,customer_name VARCHAR(190) NOT NULL,customer_address VARCHAR(255) NULL,customer_jib VARCHAR(30) NULL,customer_pdv VARCHAR(30) NULL,status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',valid_until DATE NULL,notes TEXT NULL,subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,tax_rate DECIMAL(6,2) NOT NULL DEFAULT 17,tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,total DECIMAL(12,2) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,CONSTRAINT fk_offer_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS offer_items (id INT AUTO_INCREMENT PRIMARY KEY,offer_id INT NOT NULL,description VARCHAR(500) NOT NULL,qty DECIMAL(12,3) NOT NULL DEFAULT 1,unit VARCHAR(30) NOT NULL DEFAULT 'kom',unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,discount_pct DECIMAL(6,2) NOT NULL DEFAULT 0,line_total DECIMAL(12,2) NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,CONSTRAINT fk_offer_item FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 $pdo->exec("CREATE TABLE IF NOT EXISTS commercial_modules (id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(50) NOT NULL UNIQUE,module_key VARCHAR(80) NOT NULL,name VARCHAR(190) NOT NULL,description TEXT NULL,implementation_price DECIMAL(12,2) NOT NULL DEFAULT 0,monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,partner_commission_pct DECIMAL(6,2) NOT NULL DEFAULT 0,referral_bonus DECIMAL(12,2) NOT NULL DEFAULT 0,active TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS accountant_partners (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(190) NOT NULL,company_name VARCHAR(190) NULL,email VARCHAR(190) NULL,phone VARCHAR(80) NULL,commission_pct DECIMAL(6,2) NOT NULL DEFAULT 20,active TINYINT NOT NULL DEFAULT 1,notes TEXT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS accountant_referrals (id INT AUTO_INCREMENT PRIMARY KEY,accountant_partner_id INT NOT NULL,tenant_id INT NULL,commercial_module_id INT NOT NULL,customer_name VARCHAR(190) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'LEAD',billing_cycle VARCHAR(20) NOT NULL DEFAULT 'MONTHLY',module_net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,commission_pct DECIMAL(6,2) NOT NULL DEFAULT 0,commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,referral_bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,paid_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(accountant_partner_id),INDEX(tenant_id),INDEX(commercial_module_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 if((int)$pdo->query('SELECT COUNT(*) FROM price_packages')->fetchColumn()===0){$s=$pdo->prepare('INSERT INTO price_packages(code,name,description,implementation_price,monthly_price,semiannual_price,annual_price,support_minutes,active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,NOW(),NOW())');$s->execute(['START','START','Osnovno poslovanje, računi, partneri i skladište',900,69,390,690,30,10]);$s->execute(['BUSINESS','BUSINESS','Kompletna nabava, kalkulacije, POS, fiskalizacija, KUF/KIF, PDV i financije',1500,119,650,1190,60,20]);$s->execute(['PRO','PRO','Više lokacija i blagajni, napredne funkcije i integracije',2200,199,1100,1990,90,30]);}
 if((int)$pdo->query('SELECT COUNT(*) FROM commercial_modules')->fetchColumn()===0){$s=$pdo->prepare('INSERT INTO commercial_modules(code,module_key,name,description,implementation_price,monthly_price,annual_price,partner_commission_pct,referral_bonus,active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())');$s->execute(['ACCOUNTANT','accountant','Centar knjigovođe','Odvojeni portal/modul za suradnju firme i knjigovođe: pregled KUF/KIF i PDV pripreme, razmjena dokumenata, kontrole, napomene i korekcije.',0,49,490,20,100,10]);}
 $seed=$pdo->prepare('INSERT IGNORE INTO commercial_modules(code,module_key,name,description,implementation_price,monthly_price,annual_price,partner_commission_pct,referral_bonus,active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())');
 $seed->execute(['MOBILE','mobile','Mobile Workspace','Mobilni rad po ulozi, skeniranje i brze operativne akcije.',0,19,190,0,0,20]);
 $seed->execute(['WAREHOUSE','warehouse','Advanced Warehouse','Barcode/QR, inbound, put-away, picking, bin lokacije i inventura.',290,49,490,0,0,30]);
 $seed->execute(['ANALYTICS','analytics','Management / BI','KPI dashboardi, trendovi i upozorenja za upravu.',0,29,290,0,0,40]);
 if((int)$pdo->query('SELECT COUNT(*) FROM service_catalog')->fetchColumn()===0){$s=$pdo->prepare('INSERT INTO service_catalog(code,name,description,unit,price,active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,1,?,NOW(),NOW())');$s->execute(['SUPPORT','Dodatna podrška / konfiguracija','Rad izvan uključenih minuta podrške','sat',60,10]);$s->execute(['CUSTOM_DEV','Razvoj posebne funkcionalnosti','Programiranje funkcionalnosti specifične za klijenta','sat',90,20]);$s->execute(['DATA_MIGRATION','Napredna migracija i čišćenje podataka','Migracija izvan osnovnog Excel importa','sat',60,30]);}
}
function money_bam(float $v):string{return number_format($v,2,',','.').' KM';}
function next_offer_no():string{$prefix='P-'.date('Y').'-';$last=q('SELECT offer_no FROM offers WHERE offer_no LIKE ? ORDER BY id DESC LIMIT 1',[$prefix.'%'])->fetchColumn();$n=$last?(int)substr((string)$last,-4)+1:1;return $prefix.str_pad((string)$n,4,'0',STR_PAD_LEFT);}

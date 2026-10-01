<?php
require __DIR__.'/app/core.php';
if(installed()){header('Location: ./?page=dashboard');exit;}
/* v1.7.1: instaler se ne može ponovno pokrenuti ako je platforma već bila instalirana (npr. nestao config.local.php). */
if(install_locked()){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo "Control Center je već instaliran (storage/installed.lock).\nVrati config.local.php iz backupa. Ponovna instalacija je moguća tek nakon ručnog brisanja storage/installed.lock.\n";exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(strlen((string)($_POST['admin_pass']??''))<12)throw new RuntimeException('Admin lozinka mora imati najmanje 12 znakova.');
  $h=trim($_POST['db_host']?:'localhost');$port=(int)($_POST['db_port']?:3306);$name=trim($_POST['db_name']);$du=trim($_POST['db_user']);$dp=(string)$_POST['db_pass'];
  $pdo=new PDO("mysql:host=$h;port=$port;dbname=$name;charset=utf8mb4",$du,$dp,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $sql=file_get_contents(__DIR__.'/app/schema.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $s)$pdo->exec($s);
  $hash=password_hash((string)$_POST['admin_pass'],PASSWORD_DEFAULT);$st=$pdo->prepare('INSERT INTO users(name,email,password_hash,role,active,created_at) VALUES(?,?,?,?,1,NOW())');$st->execute([trim($_POST['admin_name']),trim($_POST['admin_email']),$hash,'SUPERADMIN']);
  $tenants=[['AQMC','aqmc','Moto / trgovina i servis','https://autodms.m-m-c.ba','5.11.14','TRING','',''],['Optika Isić','optika-isic','Optika','https://optika.mmc.ba','5.11.14','TRING','','']];
  $ti=$pdo->prepare('INSERT INTO tenants(name,slug,industry,base_url,installed_version,target_version,fiscal_driver,jib,pdv,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,"ACTIVE",NOW(),NOW())');
  $mi=$pdo->prepare('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(?,?,1)');
  foreach($tenants as $t){$ti->execute([$t[0],$t[1],$t[2],$t[3],$t[4],$t[4],$t[5],$t[6],$t[7]]);$id=(int)$pdo->lastInsertId();$mods=['core','partners','inventory','purchasing','sales','pos','cash','finance','vat','uio','accountant'];if($t[1]==='aqmc')$mods=array_merge($mods,['dms','service']);else $mods[]='optics';foreach($mods as $m)$mi->execute([$id,$m]);}
  $pdo->prepare('INSERT INTO core_versions(version,name,status,notes,created_at) VALUES(?,?,?,?,NOW())')->execute(['5.11.14','Accounting Finalization','CURRENT','UIO eKUF/eKIF, validator, POS→KIF, AUTO sheme, partner cleanup, kartica artikla.']);
  $cfg="<?php\nreturn ".var_export(['db_host'=>$h,'db_port'=>$port,'db_name'=>$name,'db_user'=>$du,'db_pass'=>$dp],true).";\n";if(file_put_contents(__DIR__.'/config.local.php',$cfg,LOCK_EX)===false)throw new RuntimeException('Ne mogu zapisati config.local.php.');ensure_install_lock();header('Location: ./?page=login&installed=1');exit;
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="bs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>MMC ERP instalacija</title><link rel="stylesheet" href="public/style.css"></head><body class="install"><main class="install-card"><div class="hero"><h1>MMC ERP Platform</h1><p>Centralna administracija firmi i zajedničkog Corea</p></div><?php if($error):?><div class="alert err"><?=e($error)?></div><?php endif;?><form method="post" class="grid"><label>DB host<input name="db_host" value="localhost" required></label><label>DB port<input name="db_port" value="3306" required></label><label>Naziv prazne baze<input name="db_name" required></label><label>Korisnik baze<input name="db_user" required></label><label class="wide">Lozinka baze<input type="password" name="db_pass" required></label><label>Ime administratora<input name="admin_name" value="Administrator" required></label><label>Admin email<input type="email" name="admin_email" required></label><label class="wide">Admin lozinka (min 12 znakova)<input type="password" minlength="12" name="admin_pass" required></label><div class="wide seed"><b>Automatski će biti registrirani:</b><br>AQMC · autodms.m-m-c.ba<br>Optika Isić · optika.mmc.ba</div><button class="btn primary wide">Instaliraj Control Center</button></form></main></body></html>

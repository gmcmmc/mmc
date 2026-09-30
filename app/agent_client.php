<?php
declare(strict_types=1);
function agent_schema():void{
 static $done=false;if($done)return;$done=true;
 $pdo=db();
 $cols=[];foreach($pdo->query('SHOW COLUMNS FROM tenants')->fetchAll() as $r)$cols[$r['Field']]=1;
 foreach([
  'agent_url'=>'VARCHAR(255) NULL','agent_secret'=>'VARCHAR(255) NULL','agent_version'=>'VARCHAR(50) NULL',
  'profile'=>'VARCHAR(30) NULL','db_user'=>'VARCHAR(100) NULL','address'=>'VARCHAR(190) NULL',
  'city'=>'VARCHAR(120) NULL','postal_code'=>'VARCHAR(30) NULL','country'=>'VARCHAR(120) NULL'
] as $c=>$def)if(!isset($cols[$c]))$pdo->exec("ALTER TABLE tenants ADD COLUMN $c $def");
 $pdo->exec("CREATE TABLE IF NOT EXISTS update_packages (id INT AUTO_INCREMENT PRIMARY KEY,version VARCHAR(50) NOT NULL,name VARCHAR(190) NOT NULL,package_file VARCHAR(255) NOT NULL,checksum VARCHAR(64) NOT NULL,size_bytes BIGINT NOT NULL DEFAULT 0,notes TEXT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pcols=[];foreach($pdo->query('SHOW COLUMNS FROM update_packages')->fetchAll() as $r)$pcols[$r['Field']]=1;
 foreach(['theme'=>'VARCHAR(190) NULL','short_description'=>'VARCHAR(500) NULL','manifest_json'=>'LONGTEXT NULL','archived_at'=>'DATETIME NULL','target_app'=>'VARCHAR(40) NULL','package_scope'=>'VARCHAR(20) NULL','release_channel'=>"VARCHAR(20) NOT NULL DEFAULT 'DRAFT'"] as $c=>$def)if(!isset($pcols[$c]))$pdo->exec("ALTER TABLE update_packages ADD COLUMN $c $def");
 $pdo->exec("CREATE TABLE IF NOT EXISTS update_rollouts (id INT AUTO_INCREMENT PRIMARY KEY,package_id INT NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'RUNNING',tenant_count INT NOT NULL DEFAULT 0,success_count INT NOT NULL DEFAULT 0,failed_count INT NOT NULL DEFAULT 0,started_by VARCHAR(190) NULL,started_at DATETIME NOT NULL,finished_at DATETIME NULL,INDEX(package_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS update_rollout_items (id INT AUTO_INCREMENT PRIMARY KEY,rollout_id INT NOT NULL,deployment_id INT NULL,tenant_id INT NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'PENDING',message TEXT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(rollout_id),INDEX(tenant_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function agent_request(array $t,array $payload,int $timeout=25):array{
 $url=trim((string)($t['agent_url']??''));$secret=(string)($t['agent_secret']??'');
 if($url===''||$secret==='')throw new RuntimeException('Agent URL ili tajni ključ nisu podešeni.');
 $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$sig=hash_hmac('sha256',$body,$secret);
 if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL ekstenzija nije dostupna na Control Center serveru.');
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>$timeout,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-MMC-Signature: '.$sig,'X-MMC-Control-Center: erp.mmc.ba'],CURLOPT_POSTFIELDS=>$body]);
 $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false||$err)throw new RuntimeException('Agent veza nije uspjela: '.$err);
 $r=json_decode((string)$raw,true);if(!is_array($r))throw new RuntimeException('Agent je vratio neispravan odgovor (HTTP '.$code.').');
 if($code>=400||empty($r['ok']))throw new RuntimeException((string)($r['message']??('Agent HTTP '.$code)));
 return $r;
}
function package_dir():string{$d=__DIR__.'/../storage/update-packages';if(!is_dir($d)&&!mkdir($d,0770,true)&&!is_dir($d))throw new RuntimeException('Ne mogu kreirati storage/update-packages.');return $d;}
function read_update_manifest(string $zipPath):array{
 $raw=false;
 // First choice: ZipArchive when available.
 if(class_exists('ZipArchive')){
   $z=new ZipArchive();
   if($z->open($zipPath)===true){
     $raw=$z->getFromName('mmc-update.json');
     if($raw===false){
       for($i=0;$i<$z->numFiles;$i++){
         $n=(string)$z->getNameIndex($i);
         if(preg_match('~^[^/]+/mmc-update\.json$~',$n)){ $raw=$z->getFromIndex($i); break; }
       }
     }
     $z->close();
   }
 }
 // Plesk/PHP installations can have zip support without ZipArchive. Try zip://.
 if($raw===false && in_array('zip',stream_get_wrappers(),true)){
   $raw=@file_get_contents('zip://'.$zipPath.'#mmc-update.json');
 }
 if($raw===false)throw new RuntimeException('Ne mogu pročitati mmc-update.json iz ZIP-a. Provjeri da PHP ima ZIP podršku i da je manifest u rootu paketa.');
 $m=json_decode((string)$raw,true);
 if(!is_array($m))throw new RuntimeException('mmc-update.json nije ispravan JSON.');
 foreach(['version','theme','description'] as $k){
   if(trim((string)($m[$k]??''))==='')throw new RuntimeException('Manifest nema obavezno polje: '.$k);
 }
 return $m;
}
function save_update_package(array $file,string $version='',string $name='',string $notes=''):int{
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
   $codes=[UPLOAD_ERR_INI_SIZE=>'ZIP je veći od upload_max_filesize.',UPLOAD_ERR_FORM_SIZE=>'ZIP je veći od dozvoljene veličine forme.',UPLOAD_ERR_PARTIAL=>'ZIP je samo djelomično prenesen.',UPLOAD_ERR_NO_FILE=>'ZIP paket nije odabran.',UPLOAD_ERR_NO_TMP_DIR=>'PHP nema privremeni direktorij.',UPLOAD_ERR_CANT_WRITE=>'Server ne može zapisati upload.',UPLOAD_ERR_EXTENSION=>'PHP ekstenzija je zaustavila upload.'];
   throw new RuntimeException($codes[$file['error']??UPLOAD_ERR_NO_FILE]??('Upload greška #'.($file['error']??'?')));
 }
 if(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))!=='zip')throw new RuntimeException('Dozvoljen je samo ZIP paket.');
 if(($file['size']??0)>25*1024*1024)throw new RuntimeException('Paket je veći od 25 MB.');
 $tmp=(string)$file['tmp_name'];
 $m=read_update_manifest($tmp);
 $version=trim((string)$m['version']);$theme=trim((string)$m['theme']);$desc=trim((string)$m['description']);
 $name=trim((string)($m['name']??$theme));$notes=trim((string)($m['notes']??$desc));
 $safe=preg_replace('/[^A-Za-z0-9._-]+/','-',trim($version.'-'.$name)).'.zip';
 $dest=package_dir().'/'.date('Ymd-His').'-'.$safe;
 if(!@move_uploaded_file($tmp,$dest)){
   // Some Plesk/FPM setups report a valid temp file but move_uploaded_file can fail
   // because of temp/open_basedir handling. copy() is safe here after PHP upload validation.
   if(!is_uploaded_file($tmp) || !@copy($tmp,$dest))throw new RuntimeException('ZIP je primljen, ali ga Control Center ne može spremiti u zaštićeni storage: '.package_dir());
 }
 if(!is_file($dest) || filesize($dest)<1)throw new RuntimeException('ZIP nije spremljen u storage.');
 $sha=hash_file('sha256',$dest);
 $targetApp=trim((string)($m['target_app']??'all'));$scope=trim((string)($m['package_scope']??(in_array(strtolower($targetApp),['all','core','common'],true)?'common':'profile')));
 q('INSERT INTO update_packages(version,name,package_file,checksum,size_bytes,notes,theme,short_description,manifest_json,target_app,package_scope,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())',[$version,$name,basename($dest),$sha,filesize($dest),$notes,$theme,$desc,json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$targetApp,$scope]);
 return (int)db()->lastInsertId();
}
function package_row(int $id):array{$p=q('SELECT * FROM update_packages WHERE id=?',[$id])->fetch();if(!$p)throw new RuntimeException('Update paket nije pronađen.');return $p;}
function package_path(array $p):string{return package_dir().'/'.basename((string)$p['package_file']);}
function deployment_package_payload(string $zipPath):array{
 // mmc-update.json is Control Center metadata only. It must never be sent to an ERP agent.
 if(!class_exists('ZipArchive'))throw new RuntimeException('Za SMART deployment PHP mora imati ZipArchive ekstenziju.');
 $src=new ZipArchive();
 if($src->open($zipPath)!==true)throw new RuntimeException('Ne mogu otvoriti spremljeni update ZIP.');
 $tmp=tempnam(sys_get_temp_dir(),'mmc-deploy-');
 if($tmp===false){$src->close();throw new RuntimeException('Ne mogu kreirati privremeni deployment ZIP.');}
 @unlink($tmp); $tmp.='.zip';
 $dst=new ZipArchive();
 if($dst->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){$src->close();throw new RuntimeException('Ne mogu kreirati deployment ZIP.');}
 $files=0;
 for($i=0;$i<$src->numFiles;$i++){
   $name=(string)$src->getNameIndex($i);
   $norm=str_replace('\\','/',$name);
   // Manifest is metadata. Allow it in root (or one wrapper directory) but do not deploy it.
   if($norm==='mmc-update.json'||preg_match('~^[^/]+/mmc-update\.json$~',$norm))continue;
   if($norm===''||str_starts_with($norm,'/')||preg_match('~(^|/)\.\.(/|$)~',$norm)){$dst->close();$src->close();@unlink($tmp);throw new RuntimeException('Paket sadrži nedozvoljenu putanju: '.$name);}
   if(str_ends_with($norm,'/')){$dst->addEmptyDir(rtrim($norm,'/'));continue;}
   $data=$src->getFromIndex($i);
   if($data===false||!$dst->addFromString($norm,$data)){$dst->close();$src->close();@unlink($tmp);throw new RuntimeException('Ne mogu pripremiti datoteku za deployment: '.$name);}
   $files++;
 }
 $dst->close();$src->close();
 if($files<1){@unlink($tmp);throw new RuntimeException('Update ZIP nema datoteka za deployment nakon izdvajanja manifesta.');}
 $bytes=@file_get_contents($tmp);@unlink($tmp);
 if($bytes===false)throw new RuntimeException('Ne mogu pročitati pripremljeni deployment ZIP.');
 return ['package_b64'=>base64_encode($bytes),'sha256'=>hash('sha256',$bytes)];
}
function agent_status_label(array $t):string{return !empty($t['last_health_at'])&&($t['last_health_status']??'')==='ONLINE'?'ONLINE':'NIJE POVEZAN';}

function deploy_package_to_tenant(array $t,array $p):array{
 if(!package_target_ok($t,$p))throw new RuntimeException('Paket nije kompatibilan s profilom firme '.tenant_app_type($t).'.');
 $path=package_path($p);if(!is_file($path))throw new RuntimeException('ZIP paket nedostaje u storageu.');
 $from=(string)$t['installed_version'];$payload=deployment_package_payload($path);
 q('INSERT INTO deployments(tenant_id,from_version,to_version,status,started_at) VALUES(?,?,?,?,NOW())',[(int)$t['id'],$from,$p['version'],'RUNNING']);$did=(int)db()->lastInsertId();
 try{$r=agent_request($t,['action'=>'deploy','deployment_id'=>'cc-'.$did.'-'.date('YmdHis'),'to_version'=>$p['version'],'sha256'=>$payload['sha256'],'package_b64'=>$payload['package_b64']],90);q('UPDATE deployments SET status=?,backup_ref=?,message=?,finished_at=NOW() WHERE id=?',['SUCCESS',(string)($r['backup_ref']??''),(string)($r['message']??'Deployment uspješan.'),$did]);$actual=(string)($r['app_version']??$from);q('UPDATE tenants SET installed_version=?,target_version=?,last_health_at=NOW(),last_health_status=?,agent_version=?,updated_at=NOW() WHERE id=?',[$actual,$actual,'ONLINE',(string)($r['agent_version']??''),(int)$t['id']]);audit('DEPLOY_SUCCESS','deployment',$did,['tenant'=>(int)$t['id'],'package'=>(int)$p['id'],'backup_ref'=>$r['backup_ref']??'']);return ['ok'=>true,'deployment_id'=>$did,'message'=>(string)($r['message']??'Deployment uspješan.')];}
 catch(Throwable $e){q('UPDATE deployments SET status=?,message=?,finished_at=NOW() WHERE id=?',['FAILED',$e->getMessage(),$did]);audit('DEPLOY_FAILED','deployment',$did,['error'=>$e->getMessage()]);return ['ok'=>false,'deployment_id'=>$did,'message'=>$e->getMessage()];}
}

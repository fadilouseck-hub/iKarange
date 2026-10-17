<?php
/** Owner-authorized localization of an entirely synthetic database. CLI only.
 * Updates display fields only; credentials, IDs and business relationships stay intact.
 * Requires a private backup directory outside the website; never run the seed/reset script.
 */
if (PHP_SAPI !== 'cli' && !defined('SENEGAL_DEMO_AUTHORIZED_RUNNER')) { http_response_code(404); exit; }
umask(0077);
$backup = getenv('DEMO_BACKUP_DIR');
if (!$backup || !is_dir($backup) || str_starts_with(realpath($backup), realpath(dirname(__DIR__)))) {
    throw new RuntimeException('Private backup directory outside site root required.');
}
foreach (file(dirname(__DIR__).'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#') continue;
    [$k,$v] = array_pad(explode('=', $line, 2), 2, ''); putenv($k.'='.trim($v));
}
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.getenv('DB_NAME').';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$config = json_decode(file_get_contents(__DIR__.'/senegal_demo_data.json'), true, 512, JSON_THROW_ON_ERROR);
function pick($key,$n) { global $config; return $config[$key][$n % count($config[$key])]; }
$tables = [
 'adherents'=>['nom','prenom','sexe'],
 'entreprises'=>['raison_sociale','adresse'],
 'prestataires'=>['nom','type','ville','adresse','latitude','longitude'],
 'compagnies_assurance'=>['nom','adresse','contact_personne'],
 'users'=>['full_name'],
 'utilisateurs_prestataires'=>['nom_complet'],
 'organizations'=>['name','contact_name'],
];
$original=[]; $updates=[];
foreach ($tables as $table=>$fields) {
 $exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $exists->execute([$table]);
 if (!$exists->fetchColumn()) continue;
 $columns=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
 $fields=array_values(array_intersect($fields,$columns));
 $rows=$pdo->query('SELECT `id`,'.implode(',',array_map(fn($f)=>'`'.$f.'`',$fields)).' FROM `'.$table.'` ORDER BY id')->fetchAll();
 $original[$table]=$rows;
 foreach ($rows as $row) {
  $n=(int)$row['id']-1; $name=pick('male',$n).' '.pick('last',$n); $u=[];
  switch($table) {
   case 'adherents': $u=['nom'=>pick('last',$n),'prenom'=>pick(($row['sexe']??'')==='feminin'?'female':'male',$n)]; break;
   case 'entreprises':
    // Preserve the dedicated review-company marker used by the reset-password safety guard.
    $u=['raison_sociale'=>$row['raison_sociale']==='ASSUR PLUS — compte de test'?$row['raison_sociale']:pick('businesses',$n),'adresse'=>pick('places',$n)]; break;
   case 'prestataires':
    $u=['nom'=>($config['providerTypes'][$row['type']??'']??'Centre de santé').' '.pick('last',$n).'','ville'=>'Dakar','adresse'=>['Plateau, Dakar','Point E, Dakar','Mermoz, Dakar','Sacré-Cœur, Dakar'][$n%4]];
    if(in_array('latitude',$fields)) $u['latitude']=[14.668,14.693,14.712,14.721][$n%4];
    if(in_array('longitude',$fields)) $u['longitude']=[-17.438,-17.46,-17.484,-17.465][$n%4]; break;
   case 'compagnies_assurance': $u=['nom'=>['Assur Plus','Teranga Assurances — Démo','Baobab Assurance — Démo','Jamm Assurance — Démo'][$n%4],'adresse'=>pick('places',$n),'contact_personne'=>$name]; break;
   case 'users': $u=['full_name'=>$name]; break;
   case 'utilisateurs_prestataires': $u=['nom_complet'=>$name]; break;
   case 'organizations': $u=['contact_name'=>$name];
    if(preg_match('/Mauritan|Nouakchott|Nouadhibou/i',$row['name'])) $u['name']='Assur Plus — Sénégal'; break;
  }
  $u=array_intersect_key($u,array_flip($fields));
  if($u) $updates[]=['table'=>$table,'id'=>$row['id'],'fields'=>$u];
 }
}
$backupFile=$backup.'/senegal-display-before.json';
$handle=fopen($backupFile,'x'); if(!$handle) throw new RuntimeException('Backup already exists; refusing rerun.');
fwrite($handle,json_encode($original,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); fclose($handle); chmod($backupFile,0600);
$pdo->beginTransaction();
try {
 foreach($updates as $u) {
  $sql='UPDATE `'.$u['table'].'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($u['fields']))).' WHERE id=?';
  $pdo->prepare($sql)->execute([...array_values($u['fields']),$u['id']]);
 }
 $pdo->commit();
} catch(Throwable $e) { $pdo->rollBack(); throw $e; }
echo json_encode(['success'=>true,'rows'=>array_map('count',$original),'completed_at'=>gmdate('c')],JSON_THROW_ON_ERROR)."\n";

<?php
/** Remove display-only Demo/Démo text; preserves authentication and stored-file paths. */
if(PHP_SAPI!=='cli'&&!defined('ASSUR_DEMO_AUTHORIZED_RUNNER')){http_response_code(404);exit;}
umask(0077);$backup=getenv('DEMO_BACKUP_DIR');
if(!$backup||!is_dir($backup)||str_starts_with(realpath($backup),realpath(dirname(__DIR__))))throw new RuntimeException('Private backup directory required.');
foreach(file(dirname(__DIR__).'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){$line=trim($line);if(!$line||$line[0]==='#')continue;[$k,$v]=array_pad(explode('=',$line,2),2,'');putenv($k.'='.trim($v));}
$pdo=new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$allowed=['name','nom','prenom','full_name','nom_complet','raison_sociale','adresse','ville','specialites','secteur_activite','contact_name','contact_personne','notes','observations','motif','commentaire_admin','title','libelle','description','numero','matricule','ninea'];
function cleanLabel($s){
 $s=str_replace('DEMO-SN26','SN26',$s);$s=preg_replace('/\bDEMO-(?=\d)/u','AP-',$s);
 $s=preg_replace('/\b(?:demo|démo)\b/iu','',$s);
 $s=preg_replace('/[ \t]{2,}/u',' ',$s);$s=preg_replace('/\s*[—–-]\s*$/u','',$s);
 return trim($s);
}
$changes=[];$original=[];
foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
 $cols=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('id',$cols))continue;
 $fields=array_values(array_intersect($allowed,$cols));if(!$fields)continue;
 foreach($pdo->query('SELECT id,`'.implode('`,`',$fields).'` FROM `'.$table.'`')->fetchAll() as $row){
  $values=[];foreach($fields as $f)if(is_string($row[$f])&&preg_match('/demo|démo/iu',$row[$f])){ $v=cleanLabel($row[$f]);if($v!==$row[$f])$values[$f]=$v; }
  if($values){$changes[]=['table'=>$table,'id'=>$row['id'],'fields'=>$values];$original[$table][]=['id'=>$row['id'],'fields'=>array_intersect_key($row,$values)];}
 }
}
$h=fopen($backup.'/display-before.json','x');if(!$h)throw new RuntimeException('Backup exists; refusing rerun.');fwrite($h,json_encode($original,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));fclose($h);chmod($backup.'/display-before.json',0600);
$count=[];$pdo->beginTransaction();
try{foreach($changes as $c){$pdo->prepare('UPDATE `'.$c['table'].'` SET '.implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($c['fields']))).' WHERE id=?')->execute([...array_values($c['fields']),$c['id']]);$count[$c['table']]=($count[$c['table']]??0)+1;}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode(['success'=>true,'changed_rows'=>$count,'completed_at'=>gmdate('c')],JSON_THROW_ON_ERROR)."\n";

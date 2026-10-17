<?php
/** Additive fictional Senegal demo dataset. No resets, credentials, emails or external notifications. */
if (PHP_SAPI !== 'cli' && !defined('ASSUR_DEMO_AUTHORIZED_RUNNER')) { http_response_code(404); exit; }
umask(0077);
$backup = getenv('DEMO_BACKUP_DIR');
if (!$backup || !is_dir($backup) || str_starts_with(realpath($backup), realpath(dirname(__DIR__)))) throw new RuntimeException('Private backup directory required.');
foreach (file(dirname(__DIR__).'/.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
 $line=trim($line); if(!$line||$line[0]==='#')continue; [$k,$v]=array_pad(explode('=',$line,2),2,'');putenv($k.'='.trim($v));
}
$pdo=new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$cfg=json_decode(file_get_contents(__DIR__.'/senegal_demo_data.json'),true,512,JSON_THROW_ON_ERROR);
$demo=$pdo->query("SELECT id,org_id FROM adherents WHERE login='Demo'")->fetch(); if(!$demo)throw new RuntimeException('Demo account missing.');
$org=(int)$demo['org_id']; $tag='SN26';
$check=$pdo->prepare('SELECT COUNT(*) FROM entreprises WHERE org_id=? AND ninea LIKE ?');$check->execute([$org,$tag.'-%']);
if($check->fetchColumn()){echo json_encode(['success'=>true,'already_present'=>true]);return;}
$tables=['entreprises','adherents','prestataires','baremes','prises_en_charge','remboursements','factures_entreprises','factures_prestataires','primes'];
$schema=[];$before=[];$dump=[];
foreach($tables as $t){$schema[$t]=$pdo->query('SHOW COLUMNS FROM `'.$t.'`')->fetchAll(PDO::FETCH_COLUMN);$rows=$pdo->query('SELECT * FROM `'.$t.'`')->fetchAll();$dump[$t]=$rows;$before[$t]=count($rows);}
$handle=fopen($backup.'/expanded-demo-before.json','x');if(!$handle)throw new RuntimeException('Backup exists; refusing unguarded rerun.');fwrite($handle,json_encode($dump,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));fclose($handle);chmod($backup.'/expanded-demo-before.json',0600);unset($dump);
function addRow($table,$values){global $pdo,$schema;foreach(array_keys($values) as $k)if(!in_array($k,$schema[$table]))throw new RuntimeException('Unexpected schema column '.$table.'.'.$k);$pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($values)).'`) VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute(array_values($values));return (int)$pdo->lastInsertId();}
$anchor=new DateTimeImmutable('2026-10-08');
function dayAgo($days){global $anchor;return $anchor->modify('-'.$days.' days')->format('Y-m-d');}
function stampAgo($days){return dayAgo($days).' 10:00:00';}
$types=['consultation','pharmacie','analyse','imagerie','hospitalisation','dentaire','optique'];
$labels=['Consultation','Pharmacie','Analyses / Biologie','Imagerie','Hospitalisation','Soins dentaires','Optique'];
$amounts=[12000,18000,27000,35000,80000,40000,55000];
$cities=['Dakar','Thiès','Saint-Louis','Kaolack','Ziguinchor','Rufisque','Mbour','Louga','Diourbel','Tambacounda'];
$businesses=['Teranga','Baobab','Jamm','Jokko','Ndam','Sunu','Cayor','Saloum','Ndiambour','Casamance'];
$sectors=['Commerce','Agriculture','Transport','Technologie','Éducation','Tourisme','Industrie','Services','Artisanat','Énergie'];
$companies=[];$members=[];$providers=[];$counts=[];
$pdo->beginTransaction();
try {
 for($i=0;$i<20;$i++){
  $companies[]=addRow('entreprises',['org_id'=>$org,'raison_sociale'=>$businesses[$i%10].' '.$sectors[$i%10].' '.($i<10?'Sénégal':'Services').'','ninea'=>$tag.'-ENT-'.sprintf('%03d',$i+1),'secteur_activite'=>$sectors[$i%10],'adresse'=>'Centre-ville, '.$cities[$i%10],'nombre_employes'=>15,'taux_cotisation'=>3+($i%4),'taux_prise_en_charge'=>[70,80,90,100][$i%4],'date_adhesion'=>'2026-01-01','statut'=>'actif']);
  foreach($types as $j=>$type)addRow('baremes',['org_id'=>$org,'entreprise_id'=>$companies[$i],'type_acte'=>$type,'libelle'=>$labels[$j],'taux_couverture'=>$type==='hospitalisation'?100:[70,80,90,100][$i%4],'plafond_acte'=>$type==='dentaire'?300000:($type==='optique'?150000:null),'periode_plafond'=>$type==='dentaire'?'par_an':($type==='optique'?'par_2_ans':null)]);
 }
 for($i=0;$i<300;$i++){
  $female=$i%2===0;$first=$cfg[$female?'female':'male'][intdiv($i,2)%10];$last=$cfg['last'][$i%count($cfg['last'])];
  $members[]=addRow('adherents',['org_id'=>$org,'entreprise_id'=>$companies[intdiv($i,15)],'nom'=>$last,'prenom'=>$first,'matricule'=>$tag.'-ADH-'.sprintf('%04d',$i+1),'sexe'=>$female?'feminin':'masculin','date_naissance'=>sprintf('%04d-%02d-%02d',1970+($i%35),1+$i%12,1+$i%27),'categorie'=>'titulaire','plafond_annuel'=>[1000000,1500000,2000000][$i%3],'date_adhesion'=>'2026-01-01','statut'=>$i%25===0?'suspendu':'actif']);
 }
 $providerTypes=['clinique','hopital','pharmacie','laboratoire','centre_imagerie','dentiste'];$providerLabels=['Clinique','Hôpital','Pharmacie','Laboratoire','Centre d’imagerie','Cabinet dentaire'];
 for($i=0;$i<30;$i++)$providers[]=addRow('prestataires',['org_id'=>$org,'nom'=>$providerLabels[$i%6].' '.$businesses[$i%10].' '.($i+1).'','type'=>$providerTypes[$i%6],'ville'=>$cities[$i%10],'adresse'=>'Centre-ville, '.$cities[$i%10],'specialites'=>$providerLabels[$i%6].' — établissement fictif de démonstration','statut'=>'agree']);
 for($i=0;$i<600;$i++){
  $j=$i%7;$total=$amounts[$j]+($i%3)*2000;$rate=$types[$j]==='hospitalisation'?100:[70,80,90,100][intdiv($i%300,15)%4];$part=(int)round($total*$rate/100);$state=['en_attente','approuvee','reglee','rejetee','facturee'][$i%5];$ago=1+($i*7)%240;
  addRow('prises_en_charge',['org_id'=>$org,'numero'=>$tag.'-PEC-'.sprintf('%04d',$i+1),'adherent_id'=>$members[$i%300],'prestataire_id'=>$providers[$i%30],'type_acte'=>$types[$j],'date_soins'=>dayAgo($ago),'montant_total'=>$total,'taux_couverture'=>$rate,'part_ipm'=>$part,'part_adherent'=>$total-$part,'motif'=>$labels[$j].' — scénario fictif','statut'=>$state,'observations'=>'Démonstration uniquement — aucun soin réel','created_at'=>stampAgo($ago)]);
 }
 $review=$pdo->prepare("SELECT id FROM adherents WHERE org_id=? AND login='review.assurplus'");$review->execute([$org]);$reviewId=(int)$review->fetchColumn();
 for($i=0;$i<240;$i++){
  $id=$i<24?(int)$demo['id']:($i<36&&$reviewId?$reviewId:$members[($i-36+300)%300]);$j=$i%7;$amount=$amounts[$j];$state=['soumis','en_cours','valide','rejete','paye'][$i%5];$ago=7+($i*3)%210;
  addRow('remboursements',['org_id'=>$org,'adherent_id'=>$id,'numero'=>$tag.'-RMB-'.sprintf('%04d',$i+1),'type_acte'=>$types[$j],'date_soins'=>dayAgo($ago),'montant'=>$amount,'montant_rembourse'=>in_array($state,['valide','paye'])?(int)round($amount*.8):0,'motif'=>$labels[$j].' — dossier de démonstration','statut'=>$state,'commentaire_admin'=>$state==='rejete'?'Scénario fictif : justificatif incomplet':'Dossier fictif de démonstration','date_validation'=>in_array($state,['valide','paye'])?dayAgo($ago-2):null,'date_paiement'=>$state==='paye'?dayAgo($ago-4):null,'created_at'=>stampAgo($ago),'updated_at'=>stampAgo(max(0,$ago-4))]);
 }
 foreach(['factures_entreprises'=>$companies,'factures_prestataires'=>$providers] as $table=>$ids){
  foreach($ids as $i=>$id)for($m=0;$m<6;$m++){
   $amount=$table==='factures_entreprises'?150000+($i%5)*25000:85000+($i%7)*15000;$state=['en_attente','validee','partiel','payee'][($i+$m)%4];$paid=$state==='payee'?$amount:($state==='partiel'?(int)($amount/2):0);$date=sprintf('2026-%02d-01',$m+4);
   addRow($table,['org_id'=>$org,'numero'=>$tag.($table==='factures_entreprises'?'-FE-':'-FP-').sprintf('%03d-%02d',$i+1,$m+4),($table==='factures_entreprises'?'entreprise_id':'prestataire_id')=>$id,'montant_total'=>$amount,'montant_paye'=>$paid,'date_facture'=>$date,'date_echeance'=>sprintf('2026-%02d-28',$m+4),'statut'=>$state,'observations'=>'Facture fictive — ne pas encaisser ni envoyer','created_at'=>$date.' 10:00:00']);
   if($table==='factures_entreprises')addRow('primes',['org_id'=>$org,'entreprise_id'=>$id,'mois'=>sprintf('2026-%02d',$m+4),'montant'=>$amount,'nombre_adherents'=>15,'statut'=>$state==='payee'?'payee':($state==='en_attente'?'a_facturer':'facturee'),'date_paiement'=>$state==='payee'?sprintf('2026-%02d-20',$m+4):null]);
  }
 }
 foreach($tables as $t){$counts[$t]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn()-$before[$t];}
 $expected=['entreprises'=>20,'adherents'=>300,'prestataires'=>30,'baremes'=>140,'prises_en_charge'=>600,'remboursements'=>240,'factures_entreprises'=>120,'factures_prestataires'=>180,'primes'=>120];
 if($counts!==$expected)throw new RuntimeException('Inserted counts do not match expected dataset.');
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode(['success'=>true,'added'=>$counts,'demo_claims'=>24,'review_claims'=>$reviewId?12:0,'completed_at'=>gmdate('c')],JSON_THROW_ON_ERROR)."\n";

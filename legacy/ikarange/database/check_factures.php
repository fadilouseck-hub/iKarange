<?php
require dirname(__DIR__) . '/src/bootstrap.php';

$stmt = db()->query('
    SELECT fp.id, fp.numero, p.nom AS prestataire, fp.observations
    FROM factures_prestataires fp
    LEFT JOIN prestataires p ON p.id = fp.prestataire_id
    WHERE fp.org_id = 1
    LIMIT 5
');

foreach ($stmt->fetchAll() as $f) {
    echo $f['id'] . ' | ' . $f['numero'] . ' | ' . $f['prestataire']
         . ' | ' . substr($f['observations'] ?? '', 0, 80) . "\n";
}

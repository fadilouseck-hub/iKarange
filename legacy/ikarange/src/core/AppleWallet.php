<?php

/**
 * AppleWallet — generates signed .pkpass files for Apple Wallet.
 *
 * Requirements: PHP 8.1+, ext-openssl, ext-zip
 * No external dependencies (Composer not needed).
 */
class AppleWallet
{
    private string $passTypeId;
    private string $teamId;
    private string $certPath;
    private string $certKeyPath;
    private string $certPassword;
    private string $wwdrCertPath;
    private string $iconDir;

    public function __construct()
    {
        $this->passTypeId   = getenv('APPLE_PASS_TYPE_ID') ?: 'pass.sn.ikarange.member';
        $this->teamId       = getenv('APPLE_TEAM_ID') ?: '';
        $this->certPath     = basePath(getenv('APPLE_PASS_CERT') ?: 'storage/wallet/certs/pass.pem');
        $this->certKeyPath  = basePath(getenv('APPLE_PASS_CERT_KEY') ?: 'storage/wallet/certs/pass-key.pem');
        $this->certPassword = getenv('APPLE_PASS_CERT_PASSWORD') ?: '';
        $this->wwdrCertPath = basePath(getenv('APPLE_WWDR_CERT') ?: 'storage/wallet/certs/wwdr.pem');
        $this->iconDir      = basePath('storage/wallet/icons');
    }

    /**
     * Generate a .pkpass file for the given adherent data.
     *
     * @param  array  $adherent  DB row with: id, matricule, nom, prenom,
     *                           entreprise_nom, categorie, date_naissance,
     *                           date_adhesion, statut, telephone
     * @return string Raw binary content of the .pkpass ZIP
     * @throws RuntimeException on build/signing failure
     */
    public function generate(array $adherent): string
    {
        // 1. Build pass.json
        $passJson = $this->buildPassJson($adherent);

        // 2. Collect files (pass.json + icons)
        $files = $this->collectFiles($passJson);

        // 3. Build manifest.json (SHA1 hashes)
        $manifest = $this->buildManifest($files);
        $files['manifest.json'] = $manifest;

        // 4. Sign manifest → signature
        $signature = $this->signManifest($manifest);
        if ($signature !== '') {
            $files['signature'] = $signature;
        }

        // 5. Package into ZIP
        return $this->buildZip($files);
    }

    // ──────────────────────────────────────────────
    // pass.json
    // ──────────────────────────────────────────────

    private function buildPassJson(array $a): string
    {
        $fullName   = trim(($a['nom'] ?? '') . ' ' . ($a['prenom'] ?? ''));
        $matricule  = $a['matricule'] ?? '';
        $entreprise = $a['entreprise_nom'] ?? '';

        $categorie = match ($a['categorie'] ?? '') {
            'titulaire' => 'Titulaire',
            'conjoint'  => 'Conjoint',
            'enfant'    => 'Enfant',
            default     => $a['categorie'] ?? '',
        };

        $statut = match ($a['statut'] ?? '') {
            'actif'    => 'Actif',
            'inactif'  => 'Inactif',
            'suspendu' => 'Suspendu',
            default    => $a['statut'] ?? '',
        };

        $dateNaissance = $a['date_naissance'] ?? '';
        $dateAdhesion  = $a['date_adhesion'] ?? '';

        $pass = [
            'formatVersion'      => 1,
            'passTypeIdentifier' => $this->passTypeId,
            'teamIdentifier'     => $this->teamId,
            'serialNumber'       => 'IKARANGE-' . ($a['id'] ?? '0') . '-' . $matricule,
            'organizationName'   => "I'KARANGE",
            'description'        => "Carte d'adhérent I'KARANGE",
            'logoText'           => "I'KARANGÉ",

            // Colors matching the existing teal card design
            'backgroundColor' => 'rgb(26, 58, 58)',     // #1a3a3a
            'foregroundColor' => 'rgb(255, 255, 255)',   // white
            'labelColor'      => 'rgb(176, 196, 196)',   // #b0c4c4

            // QR barcode with matricule
            'barcodes' => [
                [
                    'format'          => 'PKBarcodeFormatQR',
                    'message'         => $matricule,
                    'messageEncoding' => 'iso-8859-1',
                    'altText'         => $matricule,
                ],
            ],

            // Generic pass type
            'generic' => [
                'primaryFields' => [
                    [
                        'key'   => 'memberName',
                        'label' => 'ADHÉRENT',
                        'value' => mb_strtoupper($fullName),
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key'   => 'matricule',
                        'label' => 'MATRICULE',
                        'value' => $matricule,
                    ],
                    [
                        'key'   => 'entreprise',
                        'label' => 'ENTREPRISE',
                        'value' => $entreprise,
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key'   => 'categorie',
                        'label' => 'CATÉGORIE',
                        'value' => $categorie,
                    ],
                    [
                        'key'   => 'statut',
                        'label' => 'STATUT',
                        'value' => $statut,
                    ],
                ],
                'backFields' => [
                    [
                        'key'   => 'dateNaissance',
                        'label' => 'Date de naissance',
                        'value' => $dateNaissance
                            ? date('d/m/Y', strtotime($dateNaissance))
                            : 'Non renseigné',
                    ],
                    [
                        'key'   => 'dateAdhesion',
                        'label' => "Date d'adhésion",
                        'value' => $dateAdhesion
                            ? date('d/m/Y', strtotime($dateAdhesion))
                            : 'Non renseigné',
                    ],
                    [
                        'key'   => 'telephone',
                        'label' => 'Téléphone',
                        'value' => $a['telephone'] ?? 'Non renseigné',
                    ],
                    [
                        'key'   => 'org',
                        'label' => 'Organisme',
                        'value' => "I'KARANGÉ — Gestion Assurance & Mutuelle",
                    ],
                ],
            ],
        ];

        return json_encode($pass, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ──────────────────────────────────────────────
    // Collect pass.json + icon files
    // ──────────────────────────────────────────────

    private function collectFiles(string $passJson): array
    {
        $files = ['pass.json' => $passJson];

        $iconFiles = ['icon.png', 'icon@2x.png', 'logo.png', 'logo@2x.png'];
        foreach ($iconFiles as $name) {
            $path = $this->iconDir . '/' . $name;
            if (is_file($path)) {
                $files[$name] = file_get_contents($path);
            }
        }

        if (!isset($files['icon.png'])) {
            throw new RuntimeException('icon.png manquant dans ' . $this->iconDir);
        }

        return $files;
    }

    // ──────────────────────────────────────────────
    // manifest.json  (SHA1 hashes of every file)
    // ──────────────────────────────────────────────

    private function buildManifest(array $files): string
    {
        $hashes = [];
        foreach ($files as $name => $content) {
            $hashes[$name] = sha1($content);
        }
        return json_encode($hashes, JSON_PRETTY_PRINT);
    }

    // ──────────────────────────────────────────────
    // PKCS#7 signature
    // ──────────────────────────────────────────────

    private function signManifest(string $manifest): string
    {
        // Debug bypass: skip signing when certs don't exist
        $debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
        if ($debug && !is_file($this->certPath)) {
            // Return empty — pass won't validate on device but ZIP structure is testable
            return '';
        }

        if (!is_file($this->certPath)) {
            throw new RuntimeException('Certificat pass introuvable : ' . $this->certPath);
        }
        if (!is_file($this->certKeyPath)) {
            throw new RuntimeException('Clé privée pass introuvable : ' . $this->certKeyPath);
        }
        if (!is_file($this->wwdrCertPath)) {
            throw new RuntimeException('Certificat WWDR introuvable : ' . $this->wwdrCertPath);
        }

        $cert = openssl_x509_read(file_get_contents($this->certPath));
        if ($cert === false) {
            throw new RuntimeException('Impossible de lire le certificat : ' . openssl_error_string());
        }

        $key = openssl_pkey_get_private(
            file_get_contents($this->certKeyPath),
            $this->certPassword
        );
        if ($key === false) {
            throw new RuntimeException('Impossible de lire la clé privée : ' . openssl_error_string());
        }

        $manifestTmp  = tempnam(sys_get_temp_dir(), 'pkpass_m_');
        $signatureTmp = tempnam(sys_get_temp_dir(), 'pkpass_s_');

        try {
            file_put_contents($manifestTmp, $manifest);

            $result = openssl_pkcs7_sign(
                $manifestTmp,
                $signatureTmp,
                $cert,
                $key,
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                $this->wwdrCertPath
            );

            if ($result === false) {
                throw new RuntimeException('Signature PKCS7 échouée : ' . openssl_error_string());
            }

            // Extract DER-encoded signature from S/MIME PEM output
            $pem = file_get_contents($signatureTmp);
            $signatureB64 = '';
            $inSig = false;

            foreach (explode("\n", $pem) as $line) {
                if (str_contains($line, 'filename="smime.p7s"')) {
                    $inSig = true;
                    continue;
                }
                if ($inSig) {
                    if (str_starts_with($line, '------')) {
                        break;
                    }
                    if (trim($line) === '') continue;
                    $signatureB64 .= trim($line);
                }
            }

            $der = base64_decode($signatureB64);
            if ($der === false || strlen($der) === 0) {
                throw new RuntimeException('Extraction de la signature DER échouée');
            }

            return $der;
        } finally {
            @unlink($manifestTmp);
            @unlink($signatureTmp);
        }
    }

    // ──────────────────────────────────────────────
    // Package everything into a ZIP (.pkpass)
    // ──────────────────────────────────────────────

    private function buildZip(array $files): string
    {
        // Try ZipArchive first; fall back to pure-PHP ZIP builder
        if (class_exists('ZipArchive')) {
            return $this->buildZipNative($files);
        }
        return $this->buildZipPure($files);
    }

    /** ZipArchive-based builder (when ext-zip is loaded) */
    private function buildZipNative(array $files): string
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'pkpass_') . '.zip';

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer l\'archive ZIP');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            $pkpass = file_get_contents($tmpZip);
            if ($pkpass === false) {
                throw new RuntimeException('Impossible de lire le .pkpass');
            }
            return $pkpass;
        } finally {
            @unlink($tmpZip);
        }
    }

    /** Pure-PHP ZIP builder — no ext-zip needed */
    private function buildZipPure(array $files): string
    {
        $centralDir = '';
        $localFiles = '';
        $offset     = 0;
        $count      = 0;

        foreach ($files as $name => $content) {
            $nameLen  = strlen($name);
            $dataLen  = strlen($content);
            $crc32    = crc32($content);
            $dosTime  = $this->dosTime();

            // Local file header
            $local = "\x50\x4b\x03\x04"      // signature
                . "\x14\x00"                  // version needed (2.0)
                . "\x00\x00"                  // flags
                . "\x00\x00"                  // compression (store)
                . $dosTime                    // mod time + date
                . pack('V', $crc32)           // crc-32
                . pack('V', $dataLen)         // compressed size
                . pack('V', $dataLen)         // uncompressed size
                . pack('v', $nameLen)         // filename length
                . "\x00\x00"                  // extra field length
                . $name
                . $content;

            // Central directory entry
            $central = "\x50\x4b\x01\x02"    // signature
                . "\x14\x00"                  // version made by
                . "\x14\x00"                  // version needed
                . "\x00\x00"                  // flags
                . "\x00\x00"                  // compression (store)
                . $dosTime                    // mod time + date
                . pack('V', $crc32)
                . pack('V', $dataLen)
                . pack('V', $dataLen)
                . pack('v', $nameLen)
                . "\x00\x00"                  // extra field length
                . "\x00\x00"                  // comment length
                . "\x00\x00"                  // disk number
                . "\x00\x00"                  // internal attrs
                . "\x00\x00\x00\x00"          // external attrs
                . pack('V', $offset)          // offset of local header
                . $name;

            $localFiles .= $local;
            $centralDir .= $central;
            $offset     += strlen($local);
            $count++;
        }

        // End of central directory
        $centralDirLen = strlen($centralDir);
        $eocd = "\x50\x4b\x05\x06"           // signature
            . "\x00\x00"                      // disk number
            . "\x00\x00"                      // disk with central dir
            . pack('v', $count)               // entries on disk
            . pack('v', $count)               // total entries
            . pack('V', $centralDirLen)       // central dir size
            . pack('V', $offset)              // central dir offset
            . "\x00\x00";                     // comment length

        return $localFiles . $centralDir . $eocd;
    }

    /** Current time in DOS format (2 bytes time + 2 bytes date) */
    private function dosTime(): string
    {
        $t = getdate();
        $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | (int)($t['seconds'] / 2);
        $date = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        return pack('v', $time) . pack('v', $date);
    }
}

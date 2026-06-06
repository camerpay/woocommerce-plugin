<?php
/**
 * Build script : genere le ZIP camerpay-woocommerce-vX.Y.Z.zip
 * a partir du dossier source modules/woocommerce/camerpay-woocommerce/.
 *
 * Usage (depuis la racine Laravel) :
 *   php modules/woocommerce/build-zip.php
 *
 * Le ZIP est genere dans storage/app/integrations/ (whitelist .gitignore)
 * pour etre servi via IntegrationsController.
 */

$srcDir = __DIR__ . '/camerpay-woocommerce';
$version = '1.0.0';

// Extrait la version depuis le header WP (plus fiable que hardcode)
$mainFile = $srcDir . '/camerpay.php';
if (file_exists($mainFile)) {
    $content = file_get_contents($mainFile);
    if (preg_match('/Version:\s*([0-9.]+)/i', $content, $m)) {
        $version = trim($m[1]);
    }
}

$zipName = "camerpay-woocommerce-v{$version}.zip";
$zipPath = __DIR__ . '/../../storage/app/integrations/' . $zipName;

if (file_exists($zipPath)) {
    unlink($zipPath);
    echo "Ancien ZIP supprime.\n";
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Impossible de creer le ZIP : {$zipPath}\n");
    exit(1);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
$fileCount = 0;
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $filePath     = $file->getRealPath();
    $relativePath = 'camerpay-woocommerce/' . substr($filePath, strlen($srcDir) + 1);
    // Normalise les separateurs en /
    $relativePath = str_replace('\\', '/', $relativePath);
    $zip->addFile($filePath, $relativePath);
    $fileCount++;
}

$zip->close();

echo "ZIP genere : {$zipPath}\n";
echo "Fichiers inclus : {$fileCount}\n";
echo "Taille : " . number_format(filesize($zipPath) / 1024, 1) . " Ko\n";
echo "Version : {$version}\n";

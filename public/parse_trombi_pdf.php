<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// limits.php — coller temporairement pour diagnostic
echo "upload_max_filesize : " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size       : " . ini_get('post_max_size') . "\n";
echo "memory_limit        : " . ini_get('memory_limit') . "\n";
echo "max_execution_time  : " . ini_get('max_execution_time') . "\n";
echo "max_input_time      : " . ini_get('max_input_time') . "\n";

require_once '../src/parse_trombi_pdf/autoload.php';
use Smalot\PdfParser\Parser;

$outputDir = '../data/photos_eleves/';
if (!is_dir($outputDir)) mkdir($outputDir, 0775, true);

$eleves  = [];
$log     = [];
$traite  = false;
$erreur  = '';

// ── Traitement du PDF uploadé ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    $file = $_FILES['pdf'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $erreur = "Erreur lors de l'upload (code {$file['error']}).";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $erreur = "Le fichier doit être un PDF.";
    } else {
        $tmpPath = $file['tmp_name'];
        $parser  = new Parser();
        $pdf     = $parser->parseFile($tmpPath);
        $traite  = true;

        foreach ($pdf->getPages() as $pageNum => $page) {

            $xobjects = [];
            foreach ($page->getXObjects() as $name => $xobj) {
                if (!preg_match('/^wpt\d+$/i', $name)) continue;
                if (method_exists($xobj, 'getDetails')) {
                    $details = $xobj->getDetails();
                    if (($details['Subtype'] ?? '') === 'Image') {
                        $xobjects[$name] = $xobj->getContent();
                    }
                }
            }
            if (empty($xobjects)) continue;

            $rawStream = '';
            try {
                $contentsObj = $page->get('Contents');
                if (is_array($contentsObj)) {
                    foreach ($contentsObj as $c) {
                        $rawStream .= method_exists($c, 'getContent') ? $c->getContent() : '';
                    }
                } elseif (method_exists($contentsObj, 'getContent')) {
                    $rawStream = $contentsObj->getContent();
                }
            } catch (\Exception $e) { continue; }

            if (empty($rawStream)) continue;

            $elements = parsePageStream($rawStream, $xobjects);

            foreach ($elements as $idx => $el) {
                if ($el['type'] !== 'image') continue;

                $suite = array_slice($elements, $idx + 1);
                $mots  = array_values(array_filter($suite, fn($e) =>
                    $e['type'] === 'text'
                    && !in_array($e['text'], ['Index', 'Education'])
                    && !preg_match('/^\d{4}$/', $e['text'])
                    && trim($e['text']) !== ''
                ));

                $classeTokens   = [];
                $nomTokens      = [];
                $prenomTokens   = [];
                $classeTerminee = false;

                $idxPremierMin = null;
                foreach ($mots as $i => $mot) {
                    if (!preg_match('/^[A-Z0-9\-]+$/u', $mot['text'])) {
                        $idxPremierMin = $i;
                        break;
                    }
                }

                $tousMAJ = ($idxPremierMin !== null)
                    ? array_slice($mots, 0, $idxPremierMin)
                    : $mots;

                foreach ($tousMAJ as $maj) {
                    $txt = $maj['text'];
                    if ($classeTerminee) {
                        $nomTokens[] = $txt;
                        continue;
                    }
                    $estSuffixeClasse = !empty($classeTokens) && preg_match('/^[A-Z]{1,4}$/', $txt);
                    $estDebutClasse   = preg_match('/^\d/', $txt);
                    if ($estDebutClasse || $estSuffixeClasse) {
                        $classeTokens[] = $txt;
                    } else {
                        $classeTerminee = true;
                        $nomTokens[]    = $txt;
                    }
                }

                if ($idxPremierMin !== null) {
                    $txt = $mots[$idxPremierMin]['text'];
                    $txt = preg_replace('/[©\x{00A9}]/u', '', $txt);
                    if (trim($txt) !== '') $prenomTokens[] = trim($txt);
                }

                $classe = implode('', $classeTokens);
                $nom    = implode('-', $nomTokens);
                $prenom = trim(implode(' ', $prenomTokens));

                if ($nom !== '' && $prenom !== '') {
                    $eleves[] = [
                        'classe'    => $classe,
                        'nom'       => $nom,
                        'prenom'    => $prenom,
                        'imageData' => $el['data'],
                    ];
                }
            }
        }

        // Sauvegarde
        foreach ($eleves as $e) {
            $nom    = nettoyerChaine($e['nom']);
            $prenom = nettoyerChaine($e['prenom']);
            $classe = nettoyerChaine($e['classe']);
            $ext    = (substr($e['imageData'], 0, 2) === "\xFF\xD8") ? 'jpg' : 'png';

            // Rogner la photo
            $imageFinale = rognerPortrait($e['imageData']);
            
            $dest = $outputDir . $classe . '.' . strtoupper($nom) . '.' . $prenom . '.' . $ext;
            file_put_contents($dest, $imageFinale);
            echo "✓ $dest\n";
        }
    }
}

// ════════════════════════════════════════════════════════════════════
// FONCTIONS
// ════════════════════════════════════════════════════════════════════

function parsePageStream(string $stream, array $xobjects): array {
    $elements = [];

    preg_match_all('/\/(\w+)\s+Do/', $stream, $doMatches);
    foreach ($doMatches[1] as $name) {
        if (isset($xobjects[$name])) {
            $elements[] = ['type' => 'image', 'data' => $xobjects[$name], 'name' => $name];
        }
    }

    preg_match_all(
        '/(-?[\d.]+)\s+(-?[\d.]+)\s+Td\(([^)]*)\)Tj/',
        $stream,
        $matches,
        PREG_SET_ORDER
    );

    $words   = [];
    $wordBuf = [];
    foreach ($matches as $m) {
        $dy = (float)$m[2];
        $ch = mb_convert_encoding($m[3], 'UTF-8', 'Windows-1252');
        if ($ch === ' ' || $ch === '' || $dy != 0) {
            if (!empty($wordBuf)) {
                $words[] = implode('', $wordBuf);
                $wordBuf = [];
            }
            if ($ch !== ' ' && $ch !== '') $wordBuf[] = $ch;
        } else {
            $wordBuf[] = $ch;
        }
    }
    if (!empty($wordBuf)) $words[] = implode('', $wordBuf);

    foreach ($words as $w) {
        if (trim($w) !== '') $elements[] = ['type' => 'text', 'text' => trim($w)];
    }

    return $elements;
}

function nettoyerChaine(string $str): string {
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    $str = transliterator_transliterate('Any-Latin; Latin-ASCII', $str);
    return preg_replace('/[^a-zA-Z0-9\-]/', '_', trim($str));
}


function rognerPortrait(string $imageData): string {
    $img = imagecreatefromstring($imageData);
    if (!$img) return $imageData;

    $largeur = imagesx($img);
    $hauteur = imagesy($img);

    // ── Ajustez ces 4 valeurs selon le résultat visuel ────────────
    $margeHaut   = (int)($hauteur * 0.30);
    $margeBas    = (int)($hauteur * 0.20);
    $margeGauche = (int)($largeur * 0.20);
    $margeDroite = (int)($largeur * 0.20);
    // ──────────────────────────────────────────────────────────────

    $newLargeur = $largeur - $margeGauche - $margeDroite;
    $newHauteur = $hauteur - $margeHaut   - $margeBas;

    // Sécurité : éviter une image de taille nulle ou négative
    if ($newLargeur <= 0 || $newHauteur <= 0) return $imageData;

    $crop = imagecreatetruecolor($newLargeur, $newHauteur);
    imagecopyresampled(
        $crop,    $img,
        0,        0,             // destination X, Y
        $margeGauche, $margeHaut, // source X, Y
        $newLargeur,  $newHauteur,
        $newLargeur,  $newHauteur
    );

    ob_start();
    imagejpeg($crop, null, 90);
    $output = ob_get_clean();

    imagedestroy($img);
    imagedestroy($crop);

    return $output;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Extraction trombinoscope Pronote</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #222; padding: 2rem; }
        h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: #1a1a1a; }
        .card { background: #fff; border-radius: 10px; padding: 2rem; max-width: 600px;
                box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        label { display: block; font-weight: 600; margin-bottom: .5rem; }
        .drop-zone { border: 2px dashed #ccc; border-radius: 8px; padding: 2rem;
                     text-align: center; cursor: pointer; transition: border-color .2s, background .2s; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #0066cc; background: #f0f6ff; }
        .drop-zone input[type="file"] { display: none; }
        .drop-zone .icon { font-size: 2.5rem; margin-bottom: .5rem; }
        .drop-zone .hint { color: #888; font-size: .9rem; margin-top: .4rem; }
        #file-name { margin-top: .6rem; font-size: .9rem; color: #0066cc; font-weight: 500; }
        button[type="submit"] { margin-top: 1.2rem; width: 100%; padding: .75rem;
                                background: #0066cc; color: #fff; border: none;
                                border-radius: 8px; font-size: 1rem; font-weight: 600;
                                cursor: pointer; transition: background .2s; }
        button[type="submit"]:hover { background: #0052a3; }
        .erreur { margin-top: 1rem; padding: .75rem 1rem; background: #fff0f0;
                  border-left: 4px solid #cc0000; border-radius: 4px; color: #cc0000; }
        .resultats { margin-top: 1.5rem; }
        .resultats h2 { font-size: 1.1rem; margin-bottom: .8rem; color: #1a1a1a; }
        .badge { display: inline-block; background: #e6f4ea; color: #1a7a35;
                 padding: .3rem .8rem; border-radius: 20px; font-weight: 700;
                 font-size: .95rem; margin-bottom: 1rem; }
        .liste { list-style: none; max-height: 320px; overflow-y: auto;
                 border: 1px solid #eee; border-radius: 6px; }
        .liste li { padding: .5rem .8rem; border-bottom: 1px solid #f0f0f0;
                    font-size: .85rem; font-family: monospace; }
        .liste li:last-child { border-bottom: none; }
        .liste li::before { content: "✓ "; color: #1a7a35; font-weight: bold; }
    </style>
</head>
<body>
<h1>🎓 Extraction photos — Trombinoscope Pronote</h1>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <label for="pdf-input">Fichier PDF trombinoscope</label>
        <div class="drop-zone" id="drop-zone">
            <div class="icon">📄</div>
            <div>Glissez votre PDF ici ou <strong>cliquez pour choisir</strong></div>
            <div class="hint">Fichier PDF Pronote uniquement</div>
            <input type="file" name="pdf" id="pdf-input" accept=".pdf">
        </div>
        <div id="file-name"></div>

        <?php if ($erreur): ?>
            <div class="erreur">⚠️ <?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <button type="submit">Extraire les photos</button>
    </form>

    <?php if ($traite && empty($erreur)): ?>
    <div class="resultats">
        <h2>Résultats</h2>
        <div class="badge"><?= count($eleves) ?> élève(s) traité(s)</div>
        <ul class="liste">
            <?php foreach ($log as $dest): ?>
                <li><?= htmlspecialchars(basename($dest)) ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top:.8rem;font-size:.85rem;color:#666;">
            Photos enregistrées dans <code><?= htmlspecialchars($outputDir) ?></code>
        </p>
    </div>
    <?php endif; ?>
</div>

<script>
const zone   = document.getElementById('drop-zone');
const input  = document.getElementById('pdf-input');
const label  = document.getElementById('file-name');

zone.addEventListener('click', () => input.click());

input.addEventListener('change', () => {
    if (input.files[0]) label.textContent = '📎 ' + input.files[0].name;
});

zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.pdf')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        label.textContent = '📎 ' + file.name;
    } else {
        label.textContent = '⚠️ Fichier non valide (PDF requis)';
    }
});
</script>
</body>
</html>
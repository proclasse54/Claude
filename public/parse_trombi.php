<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$outputDir = __DIR__ . '/data/photos_eleves/';
if (!is_dir($outputDir)) mkdir($outputDir, 0775, true);

$eleves = [];
$traite = false;
$erreur = '';

// ════════════════════════════════════════════════════════════════════
// TRAITEMENT DU PDF — PHP PUR, sans librairie externe
// ════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    $file = $_FILES['pdf'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $erreur = "Erreur lors de l'upload (code {$file['error']}).";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $erreur = "Le fichier doit être un PDF.";
    } else {
        $data   = file_get_contents($file['tmp_name']);
        $traite = true;

        // ── 1. Décompresser tous les objets FlateDecode ────────────────
        // Chaque objet : "N 0 obj << ... /FlateDecode ... >> stream ... endstream"
        $objMap = [];
        preg_match_all(
            '/(\d+) 0 obj\s*<<(.*?)>>\s*stream\r?\n(.*?)endstream/s',
            $data,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            $objNum = (int)$m[1];
            $header = $m[2];
            $raw    = $m[3];
            if (strpos($header, 'FlateDecode') === false) continue;
            $dec = @gzuncompress($raw);
            if ($dec === false) {
                // Essayer avec gzinflate (sans en-tête zlib)
                $dec = @gzinflate($raw);
            }
            if ($dec !== false) {
                $objMap[$objNum] = $dec;
            }
        }

        // ── 2. Trouver les pages et leur /Contents ─────────────────────
        // Format : "N 0 obj << ... /Type /Page ... /Contents M 0 R ... >>"
        $pages = []; // [page_index => contents_obj_num]
        preg_match_all(
            '/(\d+) 0 obj\s*<<(.*?)>>/s',
            $data,
            $pageMatches,
            PREG_SET_ORDER
        );
        foreach ($pageMatches as $m) {
            $body = $m[2];
            if (!preg_match('/\/Type\s*\/Page\b/', $body)) continue;
            if (!preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $body, $c)) continue;
            $pages[] = (int)$c[1]; // numéro d'objet du contenu de cette page
        }

        // ── 3. Extraire le texte de toutes les pages avec smalot ──────
        // On utilise smalot UNIQUEMENT pour le texte (ça marche bien)
        require_once '../src/parse_trombi_pdf/autoload.php';
        $parser   = new \Smalot\PdfParser\Parser();
        $pdf      = $parser->parseFile($file['tmp_name']);
        $pdfPages = $pdf->getPages();

        // ── 4. Récupérer toutes les images JPEG du PDF ────────────────
        // Les JPEG sont en clair dans le binaire : FF D8 FF ... FF D9
        $images = []; // tableau ordonné des images brutes
        $pos    = 0;
        while (true) {
            $start = strpos($data, "\xFF\xD8\xFF", $pos);
            if ($start === false) break;
            $end = strpos($data, "\xFF\xD9", $start);
            if ($end === false) break;
            $end += 2;
            $images[] = substr($data, $start, $end - $start);
            $pos = $end;
        }

        // ── 5. Associer chaque page à son image via le flux de contenu ─
        // Le flux décompressé contient "/wptN Do" si la page a une photo
        // On construit un mapping wptName => image (par ordre d'apparition)
        // wpt1 = images[0], wpt2 = images[1], etc.
        $imgIndex = 0; // index dans $images[]

        foreach ($pages as $pageIndex => $contentsObjNum) {
            if (!isset($pdfPages[$pageIndex])) continue;

            // Chercher /wptN Do dans le flux décompressé de cette page
            $wptName = null;
            if (isset($objMap[$contentsObjNum])) {
                $stream = $objMap[$contentsObjNum];
                if (preg_match('/\/(wpt\d+)\s+Do/i', $stream, $wm)) {
                    $wptName = $wm[1];
                }
            }

            // Extraire texte de la page
            $texte = $pdfPages[$pageIndex]->getText();
            $texte = preg_replace('/©[^\n]*/u', '', $texte);
            $lignes = array_values(array_filter(
                array_map('trim', preg_split('/\r\n|\r|\n/u', $texte)),
                fn($l) => $l !== ''
            ));

            if (count($lignes) < 2) {
                if ($wptName !== null) $imgIndex++;
                continue;
            }

            $classe    = preg_replace('/\s+/u', '', mb_strtoupper($lignes[0], 'UTF-8'));
            [$nom, $prenom] = splitNomPrenom($lignes[1]);

            if ($classe === '' || $nom === '' || $prenom === '') {
                if ($wptName !== null) $imgIndex++;
                continue;
            }

            // Page sans photo → on enregistre l'élève sans image, on ne touche pas imgIndex
            if ($wptName === null) {
                // élève sans photo, on ignore
                continue;
            }

            // Page avec photo → utiliser l'image courante
            $imageData = $images[$imgIndex] ?? null;
            $imgIndex++;

            if ($imageData === null) continue;

            $eleves[] = [
                'classe'    => $classe,
                'nom'       => $nom,
                'prenom'    => $prenom,
                'imageData' => $imageData,
            ];
        }

        // ── 6. Sauvegarder les photos ─────────────────────────────────
        foreach ($eleves as $e) {
            $classeFichier = nettoyerChaine($e['classe']);
            $nomFichier    = nettoyerChaine(mb_strtoupper($e['nom'], 'UTF-8'));
            $prenomFichier = nettoyerChaine($e['prenom']);
            $dest = $outputDir . $classeFichier . '.' . $nomFichier . '.' . $prenomFichier . '.jpg';
            file_put_contents($dest, rognerPortrait($e['imageData']));
        }
    }
}

// ════════════════════════════════════════════════════════════════════
// FONCTIONS
// ════════════════════════════════════════════════════════════════════

/**
 * Sépare "DE MOURA Rebecca" → ['DE MOURA', 'Rebecca']
 * Mots 100% MAJUSCULES (ou sans lettres, ex "-") = NOM
 * Premier mot avec minuscule = début du Prénom
 */
function splitNomPrenom(string $nomPrenom): array
{
    $nomPrenom   = preg_replace('/[\s\xA0]+/u', ' ', trim($nomPrenom));
    $mots        = explode(' ', $nomPrenom);
    $nomParts    = [];
    $prenomParts = [];

    foreach ($mots as $mot) {
        $lettres = preg_replace('/[^\p{L}]/u', '', $mot);
        if ($lettres === '') {
            if (empty($prenomParts)) $nomParts[] = $mot;
            else $prenomParts[] = $mot;
        } elseif ($lettres === mb_strtoupper($lettres, 'UTF-8')) {
            $nomParts[] = $mot;
        } else {
            $prenomParts[] = $mot;
        }
    }

    return [implode(' ', $nomParts), implode(' ', $prenomParts)];
}

/**
 * Retire les accents et caractères spéciaux pour le nom de fichier
 */
function nettoyerChaine(string $str): string
{
    if (class_exists('Normalizer')) {
        $str = Normalizer::normalize($str, Normalizer::FORM_D);
        $str = preg_replace('/\p{Mn}/u', '', $str);
    } else {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    }
    $str = preg_replace('/\s+/', '-', trim($str));
    return preg_replace('/[^a-zA-Z0-9\-]/', '', $str);
}

/**
 * Rogne les marges de la photo portrait (cadrage resserré sur le visage)
 */
function rognerPortrait(string $imageData): string
{
    $img = imagecreatefromstring($imageData);
    if (!$img) return $imageData;

    $w = imagesx($img);
    $h = imagesy($img);

    $margeHaut   = (int)($h * 0.30);
    $margeBas    = (int)($h * 0.20);
    $margeGauche = (int)($w * 0.20);
    $margeDroite = (int)($w * 0.20);

    $nw = $w - $margeGauche - $margeDroite;
    $nh = $h - $margeHaut   - $margeBas;

    if ($nw <= 0 || $nh <= 0) return $imageData;

    $crop = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($crop, $img, 0, 0, $margeGauche, $margeHaut, $nw, $nh, $nw, $nh);

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
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f5f5f5;color:#222;padding:2rem}
h1{font-size:1.4rem;margin-bottom:1.5rem}
.card{background:#fff;border-radius:10px;padding:2rem;max-width:620px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
label{display:block;font-weight:600;margin-bottom:.5rem}
.drop-zone{border:2px dashed #ccc;border-radius:8px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s}
.drop-zone:hover,.drop-zone.dragover{border-color:#0066cc;background:#f0f6ff}
.drop-zone input[type="file"]{display:none}
.drop-zone .icon{font-size:2.5rem;margin-bottom:.5rem}
.drop-zone .hint{color:#888;font-size:.9rem;margin-top:.4rem}
#file-name{margin-top:.6rem;font-size:.9rem;color:#0066cc;font-weight:500;min-height:1.2rem}
button[type="submit"]{margin-top:1.2rem;width:100%;padding:.75rem;background:#0066cc;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:background .2s}
button[type="submit"]:hover{background:#0052a3}
.erreur{margin-top:1rem;padding:.75rem 1rem;background:#fff0f0;border-left:4px solid #c00;border-radius:4px;color:#c00}
.resultats{margin-top:1.5rem}
.resultats h2{font-size:1.1rem;margin-bottom:.8rem}
.badge{display:inline-block;background:#e6f4ea;color:#1a7a35;padding:.3rem .9rem;border-radius:20px;font-weight:700;margin-bottom:1rem}
.liste{list-style:none;max-height:400px;overflow-y:auto;border:1px solid #eee;border-radius:6px}
.liste li{padding:.45rem .8rem;border-bottom:1px solid #f0f0f0;font-size:.82rem;font-family:monospace}
.liste li:last-child{border-bottom:none}
.liste li::before{content:"✓ ";color:#1a7a35;font-weight:bold}
</style>
</head>
<body>
<div class="card">
  <h1>🎓 Extraction photos — Trombinoscope Pronote</h1>

  <form method="post" enctype="multipart/form-data">
    <label>Fichier PDF trombinoscope Pronote</label>
    <div class="drop-zone" id="drop-zone">
      <div class="icon">📄</div>
      <div>Glissez votre PDF ici ou <strong>cliquez pour choisir</strong></div>
      <div class="hint">Trombinoscope exporté depuis Pronote</div>
      <input type="file" name="pdf" id="pdf-input" accept=".pdf">
    </div>
    <div id="file-name"></div>
    <?php if ($erreur): ?>
      <div class="erreur">⚠️ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    <button type="submit">⚙️ Extraire les photos</button>
  </form>

  <?php if ($traite && !$erreur): ?>
  <div class="resultats">
    <h2>Résultats</h2>
    <div class="badge">✅ <?= count($eleves) ?> photo(s) extraite(s)</div>
    <ul class="liste">
      <?php foreach ($eleves as $e): ?>
        <li><?= htmlspecialchars(
              nettoyerChaine($e['classe']) . '.' .
              nettoyerChaine(mb_strtoupper($e['nom'], 'UTF-8')) . '.' .
              nettoyerChaine($e['prenom']) . '.jpg'
            ) ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-top:.8rem;font-size:.85rem;color:#666">
      Enregistrées dans <code><?= htmlspecialchars($outputDir) ?></code>
    </p>
  </div>
  <?php endif; ?>
</div>

<script>
const zone  = document.getElementById('drop-zone');
const input = document.getElementById('pdf-input');
const label = document.getElementById('file-name');
zone.addEventListener('click', () => input.click());
input.addEventListener('change', () => { if (input.files[0]) label.textContent = '📎 ' + input.files[0].name; });
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('dragover');
  const f = e.dataTransfer.files[0];
  if (f && f.name.endsWith('.pdf')) {
    const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
    label.textContent = '📎 ' + f.name;
  } else { label.textContent = '⚠️ Fichier non valide (PDF requis)'; }
});
</script>
</body>
</html>
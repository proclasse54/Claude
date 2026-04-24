<?php
// src/Photo.php

/**
 * Supprime les accents pour correspondre aux noms de fichiers photos.
 * Ex : "Éloïse" → "Eloise", "Rémi" → "Remi"
 */
function removeAccents(string $str): string {
    if (class_exists('Normalizer')) {
        $str = Normalizer::normalize($str, Normalizer::FORM_D);
        $str = preg_replace('/\p{Mn}/u', '', $str);
        return $str;
    }
    // Fallback si intl non dispo
    return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
}

/**
 * Retourne l'URL de la photo d'un élève, ou null si absente.
 * Format fichier : CLASSE.NOM.Prenom.jpg
 */
function getPhotoUrl(string $classe, string $nom, string $prenom): ?string {

    $classeFichier = nettoyerChaine($classe);
    $nomFichier    = nettoyerChaine(mb_strtoupper($nom, 'UTF-8'));
    $prenomFichier = removeAccents(nettoyerChaine($prenom));
    
    // Chemin absolu réel sur le serveur
    $cheminAbsolu = '/var/www/sub-domains/proclasse/public/data/photos_eleves/' 
                    . "{$classeFichier}.{$nomFichier}.{$prenomFichier}.jpg";
    
    // URL publique retournée au navigateur
    $urlPublique = "/data/photos_eleves/{$classeFichier}.{$nomFichier}.{$prenomFichier}.jpg";
    
    return file_exists($cheminAbsolu) ? $urlPublique : null;
}

function nettoyerChaine(string $str): string
{
    if (class_exists('Normalizer')) {
        $str = Normalizer::normalize($str, Normalizer::FORM_D);
        $str = preg_replace('/\p{Mn}/u', '', $str);
    } else {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    }
    // espaces → tirets, caractères spéciaux supprimés
    $str = preg_replace('/\s+/', '-', trim($str));
    return preg_replace('/[^a-zA-Z0-9\-]/', '', $str);
}
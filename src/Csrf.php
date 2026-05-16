<?php
// src/Csrf.php
// ============================================================
//  Protection CSRF (Cross-Site Request Forgery) — CWE-352
//
//  Principe :
//    1. Un token aléatoire est généré une fois par session et stocké
//       dans $_SESSION['_csrf_token'].
//    2. Chaque formulaire HTML POST inclut ce token dans un champ caché
//       via Csrf::field().
//    3. À la soumission, Csrf::verify() compare le token reçu avec celui
//       de la session grâce à hash_equals() (comparaison en temps constant
//       pour éviter les attaques de timing).
//
//  Pourquoi SameSite=Lax seul ne suffit pas :
//    SameSite=Lax bloque les requêtes cross-site générées par des sous-ressources
//    (images, iframes) mais laisse passer les navigations de premier niveau
//    et peut être ignoré par des navigateurs anciens. Le token CSRF est la
//    défense officielle recommandée par OWASP.
//
//  Usage :
//    Dans la vue  → <?= Csrf::field() ?>   (insère un <input type="hidden">)
//    Dans le POST → Csrf::verify()         (arrête avec HTTP 419 si invalide)
// ============================================================
class Csrf
{
    /** Clé de stockage du token dans la session. */
    private const KEY = '_csrf_token';

    /**
     * Retourne le token CSRF de la session courante.
     * Génère un nouveau token (32 octets hex = 64 caractères) s'il n'existe pas encore.
     * Le token est réutilisé pour toute la durée de la session
     * (stratégie "Synchronizer Token Pattern").
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            // random_bytes() utilise le CSPRNG du système d'exploitation :
            // génère 32 octets aléatoires cryptographiquement sûrs.
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /**
     * Retourne un champ <input type="hidden"> prêt à insérer dans un formulaire.
     * La valeur est échappée pour ENT_QUOTES pour éviter toute injection HTML/XSS.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="'
             . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
             . '">';
    }

    /**
     * Vérifie le token CSRF soumis (POST ou en-tête HTTP).
     *
     * Accepte le token depuis :
     *   - $_POST['_csrf_token']         → formulaires HTML classiques
     *   - HTTP header X-CSRF-Token      → requêtes fetch/AJAX si nécessaire
     *
     * hash_equals() est indispensable à la place de === :
     * il compare les chaînes en temps constant pour prévenir les attaques
     * par analyse de timing (timing side-channel attacks).
     *
     * En cas d'échec : répond HTTP 419 (Token Expired, convention Laravel)
     * et stoppe l'exécution. Pas de redirection pour éviter les boucles.
     */
    public static function verify(): void
    {
        // Cherche le token d'abord dans le POST, puis dans l'en-tête HTTP
        $submitted = $_POST['_csrf_token']
                  ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!hash_equals(self::token(), (string) $submitted)) {
            http_response_code(419);
            // Message volontairement vague : ne pas divulguer d'informations
            // sur le mécanisme de protection à un attaquant potentiel.
            exit('Requête invalide. Veuillez recharger la page et réessayer.');
        }
    }
}

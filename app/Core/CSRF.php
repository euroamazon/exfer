<?php

namespace App\Core;

/**
 * Protection CSRF (Cross-Site Request Forgery)
 *
 * Usage dans une vue :
 *   <?= CSRF::field() ?>
 *
 * Vérification dans un contrôleur (ou middleware) :
 *   CSRF::verify();
 */
class CSRF
{
    private const TOKEN_KEY    = '_csrf_token';
    private const TOKEN_LENGTH = 32;

    /**
     * Retourne le token CSRF courant (le génère si absent)
     */
    public static function token(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Retourne un champ input hidden contenant le token CSRF
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    /**
     * Vérifie le token CSRF de la requête courante
     * Lève une exception ou redirige en cas d'échec
     */
    public static function verify(): void
    {
        $submitted = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$submitted || !hash_equals(self::token(), $submitted)) {
            // Token invalide ou expiré
            http_response_code(419);

            if (
                (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            ) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Token CSRF invalide ou expiré. Rafraîchissez la page.']);
                exit;
            }

            Session::error('Votre session a expiré. Veuillez réessayer.');
            Response::back('/');
        }

        // Régénérer le token après vérification (double submit protection)
        // Note: on garde le même token pour la durée de la session pour l'UX
    }

    /**
     * Retourne le meta tag CSRF (pour les requêtes AJAX)
     */
    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::token()) . '">';
    }
}

<?php

namespace App\Core;

/**
 * Gestion des sessions PHP
 */
class Session
{
    /**
     * Définit une valeur en session
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Lit une valeur de session
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Vérifie si une clé existe en session
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Supprime une valeur de session
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flash : stocke temporairement (lu une seule fois)
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            // Écriture
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        // Lecture et suppression
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    /**
     * Flash de succès
     */
    public static function success(string $message): void
    {
        self::flash('success', $message);
    }

    /**
     * Flash d'erreur
     */
    public static function error(string $message): void
    {
        self::flash('error', $message);
    }

    /**
     * Flash d'avertissement
     */
    public static function warning(string $message): void
    {
        self::flash('warning', $message);
    }

    /**
     * Flash d'info
     */
    public static function info(string $message): void
    {
        self::flash('info', $message);
    }

    /**
     * Récupère et efface tous les messages flash
     */
    public static function getFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    /**
     * Vide complètement la session (sans détruire le cookie)
     */
    public static function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Détruit la session complètement
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Régénère l'ID de session (protection contre la fixation)
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Sauvegarde des anciennes données de formulaire (pour repopuler après erreur)
     */
    public static function flashOld(array $data): void
    {
        self::flash('_old', $data);
    }

    /**
     * Récupère les anciennes données de formulaire
     */
    public static function old(string $key, $default = ''): mixed
    {
        $old = $_SESSION['_flash']['_old'] ?? [];
        return $old[$key] ?? $default;
    }
}

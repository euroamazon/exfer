<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

/**
 * Service d'authentification
 */
class AuthService
{
    /** Nombre max de tentatives avant blocage */
    private const MAX_ATTEMPTS   = 5;
    /** Fenêtre de temps (minutes) */
    private const WINDOW_MINUTES = 5;
    /** Durée du blocage (minutes) */
    private const BLOCK_MINUTES  = 15;

    /**
     * Tente de connecter un utilisateur
     *
     * @return array ['success' => bool, 'error' => string|null, 'user' => array|null]
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        // Vérifier si l'IP est bloquée
        $blockCheck = self::isBlocked($ip, $email);
        if ($blockCheck['blocked']) {
            return ['success' => false, 'error' => "Trop de tentatives. Réessayez dans {$blockCheck['minutes']} minute(s).", 'user' => null];
        }

        // Chercher l'utilisateur
        $user = Database::fetchOne(
            'SELECT u.*, o.name as org_name, o.slug as org_slug
             FROM users u
             JOIN organizations o ON o.id = u.organization_id
             WHERE u.email = ? AND u.is_active = 1',
            [strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($ip, $email);
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect.', 'user' => null];
        }

        // Vérifier l'organisation active
        if (!$user['org_name']) {
            return ['success' => false, 'error' => 'Votre organisation est désactivée.', 'user' => null];
        }

        // Succès : réinitialiser les tentatives
        self::clearAttempts($ip, $email);

        // Connexion
        Auth::login($user);

        // Mettre à jour last_login_at
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        // Audit
        AuditService::log('LOGIN', 'user', (int)$user['id'], null, null, (int)$user['id'], (int)$user['organization_id']);

        return ['success' => true, 'error' => null, 'user' => $user];
    }

    /**
     * Déconnecte l'utilisateur courant
     */
    public static function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            AuditService::log('LOGOUT', 'user', $user['id']);
        }
        Auth::logout();
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public static function changePassword(int $userId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
        AuditService::log('CHANGE_PASSWORD', 'user', $userId);
    }

    /**
     * Hash un mot de passe
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ─── Rate limiting ────────────────────────────────────────────────────────

    private static function isBlocked(string $ip, string $email): array
    {
        $attempt = Database::fetchOne(
            'SELECT * FROM login_attempts
             WHERE (ip_address = ? OR email = ?)
             AND blocked_until IS NOT NULL AND blocked_until > NOW()
             ORDER BY blocked_until DESC LIMIT 1',
            [$ip, strtolower($email)]
        );

        if ($attempt) {
            $minutes = (int) ceil((strtotime($attempt['blocked_until']) - time()) / 60);
            return ['blocked' => true, 'minutes' => $minutes];
        }

        return ['blocked' => false];
    }

    private static function recordFailedAttempt(string $ip, string $email): void
    {
        $email = strtolower($email);
        $windowStart = date('Y-m-d H:i:s', strtotime('-' . self::WINDOW_MINUTES . ' minutes'));

        $existing = Database::fetchOne(
            'SELECT * FROM login_attempts
             WHERE ip_address = ? AND window_start > ?
             ORDER BY id DESC LIMIT 1',
            [$ip, $windowStart]
        );

        if ($existing) {
            $count = $existing['attempt_count'] + 1;
            $blockedUntil = null;

            if ($count >= self::MAX_ATTEMPTS) {
                $blockedUntil = date('Y-m-d H:i:s', strtotime('+' . self::BLOCK_MINUTES . ' minutes'));
            }

            Database::query(
                'UPDATE login_attempts SET attempt_count = ?, email = ?, blocked_until = ? WHERE id = ?',
                [$count, $email, $blockedUntil, $existing['id']]
            );
        } else {
            Database::query(
                'INSERT INTO login_attempts (ip_address, email, attempt_count, window_start) VALUES (?, ?, 1, NOW())',
                [$ip, $email]
            );
        }
    }

    private static function clearAttempts(string $ip, string $email): void
    {
        Database::query(
            'DELETE FROM login_attempts WHERE ip_address = ? OR email = ?',
            [$ip, strtolower($email)]
        );
    }
}

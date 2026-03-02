<?php

namespace App\Core;

/**
 * Gestion de l'authentification et des droits d'accès
 *
 * L'utilisateur connecté est stocké en session sous la clé '_user'
 */
class Auth
{
    /**
     * Vérifie si un utilisateur est connecté
     */
    public static function check(): bool
    {
        return Session::has('_user');
    }

    /**
     * Retourne l'utilisateur connecté (tableau) ou null
     */
    public static function user(): ?array
    {
        return Session::get('_user');
    }

    /**
     * Retourne l'ID de l'utilisateur connecté
     */
    public static function id(): ?int
    {
        return Session::get('_user')['id'] ?? null;
    }

    /**
     * Retourne l'organization_id de l'utilisateur connecté
     */
    public static function orgId(): ?int
    {
        return Session::get('_user')['organization_id'] ?? null;
    }

    /**
     * Retourne le rôle global de l'utilisateur
     */
    public static function role(): ?string
    {
        return Session::get('_user')['role'] ?? null;
    }

    /**
     * Vérifie si l'utilisateur a le rôle ADMIN
     */
    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    /**
     * Vérifie si l'utilisateur a le rôle SUPERVISEUR ou supérieur
     */
    public static function isSuperviseur(): bool
    {
        return in_array(self::role(), ['ADMIN', 'SUPERVISEUR']);
    }

    /**
     * Vérifie si l'utilisateur a au moins le rôle spécifié
     * Hiérarchie : ADMIN > SUPERVISEUR > AGENT
     */
    public static function hasRole(string $role): bool
    {
        $hierarchy = ['AGENT' => 1, 'SUPERVISEUR' => 2, 'ADMIN' => 3];
        $userLevel = $hierarchy[self::role()] ?? 0;
        $reqLevel  = $hierarchy[$role] ?? 99;
        return $userLevel >= $reqLevel;
    }

    /**
     * Connecte un utilisateur (stockage en session)
     */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('_user', [
            'id'              => (int) $user['id'],
            'organization_id' => (int) $user['organization_id'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'role'            => $user['role'],
        ]);
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout(): void
    {
        Session::remove('_user');
        Session::regenerate();
    }

    /**
     * Exige que l'utilisateur soit connecté
     * Redirige vers /login sinon
     */
    public static function require(): void
    {
        if (!self::check()) {
            Session::flash('error', 'Veuillez vous connecter pour accéder à cette page.');
            Session::flash('redirect_after_login', $_SERVER['REQUEST_URI'] ?? '/');
            Response::redirect('/login');
        }
    }

    /**
     * Exige un rôle minimum
     */
    public static function requireRole(string $role): void
    {
        self::require();
        if (!self::hasRole($role)) {
            Response::forbidden("Vous n'avez pas les droits nécessaires pour effectuer cette action.");
        }
    }

    /**
     * Exige d'être ADMIN
     */
    public static function requireAdmin(): void
    {
        self::requireRole('ADMIN');
    }

    /**
     * Exige d'être SUPERVISEUR ou ADMIN
     */
    public static function requireSuperviseur(): void
    {
        self::requireRole('SUPERVISEUR');
    }
}

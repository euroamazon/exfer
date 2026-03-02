<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;

/**
 * Service de scope : contrôle la visibilité par campagne, site et local
 *
 * Règles :
 *  - ADMIN voit tout dans son organisation
 *  - SUPERVISEUR/AGENT voient uniquement les campagnes où ils sont membres
 *  - Si aucune ligne dans campaign_member_scope → accès complet à la campagne
 *  - Sinon accès limité aux sites/locaux listés
 */
class ScopeService
{
    /**
     * Retourne les campagnes accessibles pour un utilisateur
     */
    public static function userCampaigns(int $userId, int $orgId): array
    {
        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return [];
        }

        // ADMIN : toutes les campagnes de l'organisation
        if ($user['role'] === 'ADMIN') {
            return Database::fetchAll(
                'SELECT c.*, s.name as service_name
                 FROM campaigns c
                 LEFT JOIN services s ON s.id = c.service_id
                 WHERE c.organization_id = ?
                 ORDER BY c.created_at DESC',
                [$orgId]
            );
        }

        // SUPERVISEUR / AGENT : campagnes où ils sont membres actifs
        return Database::fetchAll(
            'SELECT c.*, s.name as service_name, cm.role_in_campaign
             FROM campaigns c
             JOIN campaign_members cm ON cm.campaign_id = c.id AND cm.user_id = ? AND cm.is_active = 1
             LEFT JOIN services s ON s.id = c.service_id
             WHERE c.organization_id = ?
             ORDER BY c.created_at DESC',
            [$userId, $orgId]
        );
    }

    /**
     * Vérifie que l'utilisateur peut accéder à la campagne
     * Retourne le membership ou null si ADMIN (qui a accès implicite)
     */
    public static function checkCampaignAccess(int $userId, int $campaignId, int $orgId): bool
    {
        // Vérifier appartenance organisation
        $campaign = Database::fetchOne(
            'SELECT id FROM campaigns WHERE id = ? AND organization_id = ?',
            [$campaignId, $orgId]
        );
        if (!$campaign) {
            return false;
        }

        $user = Database::fetchOne('SELECT role FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return false;
        }

        // ADMIN a toujours accès
        if ($user['role'] === 'ADMIN') {
            return true;
        }

        // Vérifier membership
        $member = Database::fetchOne(
            'SELECT id FROM campaign_members WHERE campaign_id = ? AND user_id = ? AND is_active = 1',
            [$campaignId, $userId]
        );

        return $member !== null;
    }

    /**
     * Vérifie que l'utilisateur peut accéder à un local spécifique
     */
    public static function checkLocationAccess(int $userId, int $locationId, int $orgId): bool
    {
        // Utilise l.organization_id (cohérent avec requireLocationAccess)
        $location = Database::fetchOne(
            'SELECT l.id, l.campaign_id, l.site_id
             FROM locations l
             WHERE l.id = ? AND l.organization_id = ?',
            [$locationId, $orgId]
        );

        if (!$location) {
            return false;
        }

        // Fast-path ADMIN via session (déjà validé par requireAuth)
        if (Auth::isAdmin()) {
            return true;
        }

        $user = Database::fetchOne('SELECT role FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return false;
        }

        // ADMIN a toujours accès
        if ($user['role'] === 'ADMIN') {
            return true;
        }

        // Vérifier membership
        $member = Database::fetchOne(
            'SELECT cm.id FROM campaign_members cm
             WHERE cm.campaign_id = ? AND cm.user_id = ? AND cm.is_active = 1',
            [$location['campaign_id'], $userId]
        );

        if (!$member) {
            return false;
        }

        // Vérifier scope
        $scopeRows = Database::fetchAll(
            'SELECT * FROM campaign_member_scope WHERE member_id = ?',
            [$member['id']]
        );

        // Pas de scope défini → accès complet
        if (empty($scopeRows)) {
            return true;
        }

        // Vérifier si le site est autorisé
        foreach ($scopeRows as $scope) {
            if ($scope['location_id'] !== null && (int)$scope['location_id'] === $locationId) {
                return true;
            }
            if ($scope['location_id'] === null && $scope['site_id'] !== null && (int)$scope['site_id'] === (int)$location['site_id']) {
                return true;
            }
            if ($scope['site_id'] === null && $scope['location_id'] === null) {
                return true; // Accès global
            }
        }

        return false;
    }

    /**
     * Filtre une liste de locaux selon le scope de l'utilisateur
     */
    public static function filterLocations(int $userId, int $campaignId, array $locations, int $orgId): array
    {
        $user = Database::fetchOne('SELECT role FROM users WHERE id = ?', [$userId]);
        if (!$user || $user['role'] === 'ADMIN') {
            return $locations; // ADMIN voit tout
        }

        $member = Database::fetchOne(
            'SELECT id FROM campaign_members WHERE campaign_id = ? AND user_id = ? AND is_active = 1',
            [$campaignId, $userId]
        );

        if (!$member) {
            return []; // Pas membre
        }

        $scopeRows = Database::fetchAll(
            'SELECT * FROM campaign_member_scope WHERE member_id = ?',
            [$member['id']]
        );

        // Pas de scope → tout voir
        if (empty($scopeRows)) {
            return $locations;
        }

        $allowedSites     = [];
        $allowedLocations = [];
        $globalAccess     = false;

        foreach ($scopeRows as $scope) {
            if ($scope['site_id'] === null && $scope['location_id'] === null) {
                $globalAccess = true;
                break;
            }
            if ($scope['location_id']) {
                $allowedLocations[] = (int)$scope['location_id'];
            } elseif ($scope['site_id']) {
                $allowedSites[] = (int)$scope['site_id'];
            }
        }

        if ($globalAccess) {
            return $locations;
        }

        return array_filter($locations, function($loc) use ($allowedSites, $allowedLocations) {
            return in_array((int)$loc['id'], $allowedLocations)
                || in_array((int)$loc['site_id'], $allowedSites);
        });
    }

    /**
     * Récupère le membership d'un utilisateur dans une campagne
     */
    public static function getMember(int $userId, int $campaignId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM campaign_members WHERE user_id = ? AND campaign_id = ? AND is_active = 1',
            [$userId, $campaignId]
        );
    }

    /**
     * Require que l'utilisateur ait accès à la campagne, sinon 403
     */
    public static function requireCampaignAccess(int $campaignId): array
    {
        $userId = Auth::id();
        $orgId  = Auth::orgId();

        if (!self::checkCampaignAccess($userId, $campaignId, $orgId)) {
            Response::forbidden("Vous n'avez pas accès à cette campagne.");
        }

        $campaign = Database::fetchOne(
            'SELECT c.*, s.name as service_name
             FROM campaigns c LEFT JOIN services s ON s.id = c.service_id
             WHERE c.id = ? AND c.organization_id = ?',
            [$campaignId, $orgId]
        );

        if (!$campaign) {
            Response::notFound('Campagne introuvable.');
        }

        return $campaign;
    }

    /**
     * Require que l'utilisateur ait accès au local, sinon 403
     */
    public static function requireLocationAccess(int $locationId): array
    {
        $userId = Auth::id();
        $orgId  = Auth::orgId();

        if (!self::checkLocationAccess($userId, $locationId, $orgId)) {
            Response::forbidden("Vous n'avez pas accès à ce local.");
        }

        $location = Database::fetchOne(
            'SELECT l.*, s.name as site_name, c.name as campaign_name, c.config as campaign_config
             FROM locations l
             LEFT JOIN sites s ON s.id = l.site_id
             JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.id = ? AND l.organization_id = ?',
            [$locationId, $orgId]
        );

        if (!$location) {
            Response::notFound('Local introuvable.');
        }

        return $location;
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\ScopeService;

class CampaignController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        $orgId    = Auth::orgId();
        $userId   = Auth::id();
        $campaigns = ScopeService::userCampaigns($userId, $orgId);

        $this->render('campaigns/index', [
            'campaigns' => $campaigns,
            'pageTitle' => 'Campagnes d\'inventaire',
        ]);
    }

    public function create(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        $orgId    = Auth::orgId();
        $services = Database::fetchAll('SELECT * FROM services WHERE organization_id = ? AND is_active = 1 ORDER BY name', [$orgId]);

        $this->render('campaigns/form', [
            'campaign' => null,
            'services' => $services,
            'pageTitle'=> 'Nouvelle campagne',
        ]);
    }

    public function store(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();

        $orgId = Auth::orgId();

        $errors = $request->validate([
            'name' => 'required|max:255',
        ]);

        if ($errors) {
            Session::flash('errors', $errors);
            Session::flashOld($request->allPost());
            Response::redirect('/campaigns/create');
        }

        // Construire la configuration
        $config = [
            'code_length'             => (int)($request->post('code_length', 6)),
            'code_type'               => $request->post('code_type', 'NUMERIC'),
            'min_code'                => $request->post('min_code') ?: null,
            'max_code'                => $request->post('max_code') ?: null,
            'unique_scope'            => $request->post('unique_scope', 'ORGANIZATION'),
            'manual_entry_enabled'    => (bool)$request->post('manual_entry_enabled'),
            'manual_entry_mode'       => $request->post('manual_entry_mode', 'FLEX_WITH_ANOMALY'),
            'label_roll_enabled'      => (bool)$request->post('label_roll_enabled'),
            'vision_enabled'          => (bool)$request->post('vision_enabled'),
            'vision_threshold'        => (float)($request->post('vision_threshold', 0.75)),
            'vision_mode'             => $request->post('vision_mode', 'WARNING'),
            'require_photo'           => (bool)$request->post('require_photo'),
            'require_serial'          => (bool)$request->post('require_serial'),
            'duplicate_copy_photos'   => (bool)$request->post('duplicate_copy_photos'),
            'duplicate_reset_fields'  => $request->post('duplicate_reset_fields') ? explode(',', $request->post('duplicate_reset_fields')) : [],
        ];

        $id = Database::insert('campaigns', [
            'organization_id' => $orgId,
            'service_id'      => $request->post('service_id') ?: null,
            'name'            => $request->post('name'),
            'code'            => $request->post('code') ?: null,
            'description'     => $request->post('description') ?: null,
            'status'          => 'DRAFT',
            'config'          => json_encode($config),
            'created_by'      => Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // Créer les colonnes système par défaut
        $this->createDefaultColumns((int)$id, $orgId);

        AuditService::log('CREATE_CAMPAIGN', 'campaign', (int)$id, null, ['name' => $request->post('name')]);
        Session::success('Campagne créée avec succès.');
        Response::redirect('/campaigns/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);
        $orgId    = Auth::orgId();

        $sites     = Database::fetchAll('SELECT * FROM sites WHERE campaign_id = ? AND is_active = 1 ORDER BY name', [$campaign['id']]);
        $locations = Database::fetchAll(
            'SELECT l.*, s.name as site_name,
                    (SELECT COUNT(*) FROM inventory_items WHERE location_id = l.id) as items_count,
                    (SELECT COUNT(*) FROM anomalies WHERE location_id = l.id AND status IN ("OPEN","INVESTIGATION")) as anomaly_count
             FROM locations l
             LEFT JOIN sites s ON s.id = l.site_id
             WHERE l.campaign_id = ? ORDER BY s.name, l.code_local',
            [$campaign['id']]
        );

        // Stats campagne
        $stats = [
            'total_locations'     => count($locations),
            'validated_locations' => count(array_filter($locations, fn($l) => $l['status'] === 'VALIDATED')),
            'total_items'         => Database::fetchScalar('SELECT COUNT(*) FROM inventory_items WHERE campaign_id = ?', [$campaign['id']]),
            'open_anomalies'      => Database::fetchScalar('SELECT COUNT(*) FROM anomalies WHERE campaign_id = ? AND status IN ("OPEN","INVESTIGATION")', [$campaign['id']]),
            'open_rolls'          => Database::fetchScalar('SELECT COUNT(*) FROM label_rolls WHERE campaign_id = ? AND status = "OPEN"', [$campaign['id']]),
        ];

        // Filtrer par scope
        $filteredLocations = ScopeService::filterLocations(Auth::id(), $campaign['id'], $locations, $orgId);

        $this->render('campaigns/show', [
            'campaign'  => $campaign,
            'sites'     => $sites,
            'locations' => $filteredLocations,
            'stats'     => $stats,
            'pageTitle' => $campaign['name'],
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);

        if ($campaign['status'] === 'CLOSED') {
            Session::error('Une campagne clôturée ne peut pas être modifiée.');
            Response::redirect('/campaigns/' . $campaign['id']);
        }

        $orgId    = Auth::orgId();
        $services = Database::fetchAll('SELECT * FROM services WHERE organization_id = ? AND is_active = 1 ORDER BY name', [$orgId]);
        $config   = json_decode($campaign['config'] ?? '{}', true) ?: [];

        $this->render('campaigns/form', [
            'campaign'  => $campaign,
            'config'    => $config,
            'services'  => $services,
            'pageTitle' => 'Modifier la campagne',
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();

        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);

        if ($campaign['status'] === 'CLOSED') {
            Response::forbidden('Campagne clôturée.');
        }

        $errors = $request->validate(['name' => 'required|max:255']);
        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/campaigns/' . $campaign['id'] . '/edit');
        }

        $config = [
            'code_length'            => (int)($request->post('code_length', 6)),
            'code_type'              => $request->post('code_type', 'NUMERIC'),
            'min_code'               => $request->post('min_code') ?: null,
            'max_code'               => $request->post('max_code') ?: null,
            'unique_scope'           => $request->post('unique_scope', 'ORGANIZATION'),
            'manual_entry_enabled'   => (bool)$request->post('manual_entry_enabled'),
            'manual_entry_mode'      => $request->post('manual_entry_mode', 'FLEX_WITH_ANOMALY'),
            'label_roll_enabled'     => (bool)$request->post('label_roll_enabled'),
            'vision_enabled'         => (bool)$request->post('vision_enabled'),
            'vision_threshold'       => (float)($request->post('vision_threshold', 0.75)),
            'vision_mode'            => $request->post('vision_mode', 'WARNING'),
            'require_photo'          => (bool)$request->post('require_photo'),
            'require_serial'         => (bool)$request->post('require_serial'),
            'duplicate_copy_photos'  => (bool)$request->post('duplicate_copy_photos'),
            'duplicate_reset_fields' => $request->post('duplicate_reset_fields') ? explode(',', $request->post('duplicate_reset_fields')) : [],
        ];

        $old = ['name' => $campaign['name']];
        Database::update('campaigns', [
            'service_id'  => $request->post('service_id') ?: null,
            'name'        => $request->post('name'),
            'code'        => $request->post('code') ?: null,
            'description' => $request->post('description') ?: null,
            'config'      => json_encode($config),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $campaign['id']]);

        AuditService::log('UPDATE_CAMPAIGN', 'campaign', $campaign['id'], $old, ['name' => $request->post('name')]);
        Session::success('Campagne mise à jour.');
        Response::redirect('/campaigns/' . $campaign['id']);
    }

    public function start(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);

        if ($campaign['status'] !== 'DRAFT') {
            Session::error('Seule une campagne en brouillon peut être démarrée.');
            Response::redirect('/campaigns/' . $campaign['id']);
        }

        Database::update('campaigns', ['status' => 'ACTIVE', 'started_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $campaign['id']]);
        AuditService::log('START_CAMPAIGN', 'campaign', $campaign['id']);
        Session::success('Campagne démarrée avec succès.');
        Response::redirect('/campaigns/' . $campaign['id']);
    }

    public function close(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);

        if ($campaign['status'] !== 'ACTIVE') {
            Session::error('Seule une campagne active peut être clôturée.');
            Response::redirect('/campaigns/' . $campaign['id']);
        }

        Database::update('campaigns', ['status' => 'CLOSED', 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $campaign['id']]);
        AuditService::log('CLOSE_CAMPAIGN', 'campaign', $campaign['id']);
        Session::success('Campagne clôturée.');
        Response::redirect('/campaigns/' . $campaign['id']);
    }

    public function duplicate(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $original = ScopeService::requireCampaignAccess((int)$params['id']);
        $orgId    = Auth::orgId();

        $newId = Database::insert('campaigns', [
            'organization_id' => $orgId,
            'service_id'      => $original['service_id'],
            'name'            => $original['name'] . ' (copie)',
            'code'            => null,
            'description'     => $original['description'],
            'status'          => 'DRAFT',
            'config'          => $original['config'],
            'created_by'      => Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // Dupliquer les colonnes dynamiques
        $columns = Database::fetchAll('SELECT * FROM dynamic_columns WHERE campaign_id = ?', [$original['id']]);
        foreach ($columns as $col) {
            Database::insert('dynamic_columns', [
                'organization_id' => $orgId,
                'campaign_id'     => (int)$newId,
                'column_key'      => $col['column_key'],
                'label'           => $col['label'],
                'type'            => $col['type'],
                'options_json'    => $col['options_json'],
                'validation_json' => $col['validation_json'],
                'position'        => $col['position'],
                'is_system'       => $col['is_system'],
                'is_active'       => 1,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        AuditService::log('DUPLICATE_CAMPAIGN', 'campaign', (int)$newId, null, ['duplicated_from' => $original['id']]);
        Session::success('Campagne dupliquée avec succès.');
        Response::redirect('/campaigns/' . $newId . '/edit');
    }

    public function members(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);
        $orgId    = Auth::orgId();

        $members = Database::fetchAll(
            'SELECT cm.*, u.name as user_name, u.email as user_email, u.role as user_role
             FROM campaign_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.campaign_id = ? AND cm.is_active = 1
             ORDER BY cm.role_in_campaign, u.name',
            [$campaign['id']]
        );

        // Enrichir chaque membre avec les sites et locaux de son scope
        $sitesMap = [];
        foreach (Database::fetchAll('SELECT * FROM sites WHERE campaign_id = ?', [$campaign['id']]) as $s) {
            $sitesMap[$s['id']] = $s['name'];
        }
        $locationsMap = [];
        foreach (Database::fetchAll('SELECT id, code_local, designation_local FROM locations WHERE campaign_id = ? ORDER BY code_local', [$campaign['id']]) as $l) {
            $locationsMap[$l['id']] = $l['code_local'] . ($l['designation_local'] ? ' — ' . $l['designation_local'] : '');
        }
        foreach ($members as &$member) {
            $scopeRows = Database::fetchAll(
                'SELECT * FROM campaign_member_scope WHERE member_id = ?',
                [$member['id']]
            );
            $member['scope_sites']     = [];
            $member['scope_locations'] = [];
            foreach ($scopeRows as $sr) {
                if ($sr['site_id'] && isset($sitesMap[$sr['site_id']])) {
                    $member['scope_sites'][] = $sitesMap[$sr['site_id']];
                }
                if ($sr['location_id'] && isset($locationsMap[$sr['location_id']])) {
                    $member['scope_locations'][] = $locationsMap[$sr['location_id']];
                }
            }
        }
        unset($member);

        $availableUsers = Database::fetchAll(
            'SELECT u.* FROM users u
             WHERE u.organization_id = ? AND u.is_active = 1
             AND u.id NOT IN (SELECT user_id FROM campaign_members WHERE campaign_id = ? AND is_active = 1)
             ORDER BY u.name',
            [$orgId, $campaign['id']]
        );

        $this->render('campaigns/members', [
            'campaign'       => $campaign,
            'members'        => $members,
            'availableUsers' => $availableUsers,
            'sites'          => Database::fetchAll('SELECT * FROM sites WHERE campaign_id = ? ORDER BY name', [$campaign['id']]),
            'locations'      => Database::fetchAll('SELECT id, code_local, designation_local FROM locations WHERE campaign_id = ? ORDER BY code_local', [$campaign['id']]),
            'pageTitle'      => 'Membres de la campagne',
        ]);
    }

    public function addMember(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['id']);
        $orgId    = Auth::orgId();

        $userId = (int)$request->post('user_id');
        $role   = $request->post('role_in_campaign', 'AGENT');

        // Vérifier que l'utilisateur appartient à l'organisation
        $user = Database::fetchOne('SELECT id FROM users WHERE id = ? AND organization_id = ?', [$userId, $orgId]);
        if (!$user) {
            Session::error('Utilisateur invalide.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/members');
        }

        // Vérifier si déjà membre
        $existing = Database::fetchOne('SELECT id FROM campaign_members WHERE campaign_id = ? AND user_id = ?', [$campaign['id'], $userId]);
        if ($existing) {
            Database::update('campaign_members', ['is_active' => 1, 'role_in_campaign' => $role, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $existing['id']]);
        } else {
            $memberId = Database::insert('campaign_members', [
                'organization_id'  => $orgId,
                'campaign_id'      => $campaign['id'],
                'user_id'          => $userId,
                'role_in_campaign' => $role,
                'is_active'        => 1,
                'assigned_by'      => Auth::id(),
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            // Gérer le scope (le formulaire envoie site_ids[] et scope_locations[])
            $siteIds     = $request->post('site_ids') ? (array)$request->post('site_ids') : [];
            $locationIds = $request->post('scope_locations') ? (array)$request->post('scope_locations') : [];

            foreach ($siteIds as $siteId) {
                Database::insert('campaign_member_scope', ['member_id' => $memberId, 'site_id' => (int)$siteId, 'location_id' => null, 'created_at' => date('Y-m-d H:i:s')]);
            }
            foreach ($locationIds as $locationId) {
                Database::insert('campaign_member_scope', ['member_id' => $memberId, 'site_id' => null, 'location_id' => (int)$locationId, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }

        AuditService::log('ADD_CAMPAIGN_MEMBER', 'campaign', $campaign['id'], null, ['user_id' => $userId, 'role' => $role]);
        Session::success('Membre ajouté à la campagne.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/members');
    }

    public function removeMember(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        Database::update('campaign_members', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int)$params['mid']]);
        AuditService::log('REMOVE_CAMPAIGN_MEMBER', 'campaign', $campaign['id']);
        Session::success('Membre retiré de la campagne.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/members');
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function createDefaultColumns(int $campaignId, int $orgId): void
    {
        $defaults = [
            ['column_key' => 'etat', 'label' => 'État', 'type' => 'select',
             'options_json' => json_encode([
                 ['value' => 'bon', 'label' => 'Bon état'],
                 ['value' => 'moyen', 'label' => 'État moyen'],
                 ['value' => 'mauvais', 'label' => 'Mauvais état'],
                 ['value' => 'hors_service', 'label' => 'Hors service'],
             ]),
             'validation_json' => json_encode(['required' => false]),
             'position' => 1,
            ],
            ['column_key' => 'valeur_acquisition', 'label' => 'Valeur d\'acquisition', 'type' => 'number',
             'options_json' => null, 'validation_json' => null, 'position' => 2,
            ],
            ['column_key' => 'annee_acquisition', 'label' => 'Année d\'acquisition', 'type' => 'number',
             'options_json' => null, 'validation_json' => json_encode(['min' => 1900, 'max' => 2100]), 'position' => 3,
            ],
            ['column_key' => 'observations', 'label' => 'Observations', 'type' => 'text',
             'options_json' => null, 'validation_json' => null, 'position' => 4,
            ],
        ];

        foreach ($defaults as $col) {
            Database::insert('dynamic_columns', array_merge($col, [
                'organization_id' => $orgId,
                'campaign_id'     => $campaignId,
                'is_system'       => 0,
                'is_active'       => 1,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]));
        }
    }
}

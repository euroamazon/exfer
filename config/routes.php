<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\OrganizationController;
use App\Controllers\UserController;
use App\Controllers\ServiceController;
use App\Controllers\CampaignController;
use App\Controllers\SiteController;
use App\Controllers\LocationController;
use App\Controllers\InventoryController;
use App\Controllers\ColumnController;
use App\Controllers\LabelRollController;
use App\Controllers\AnomalyController;
use App\Controllers\ImportController;
use App\Controllers\ExportController;

/** @var \App\Core\Router $router */

// ─── Auth ─────────────────────────────────────────────────────────────────────
$router->get('/login',  [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout',[AuthController::class, 'logout']);

// ─── Dashboard ────────────────────────────────────────────────────────────────
$router->get('/',          [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

// ─── Profile ──────────────────────────────────────────────────────────────────
$router->get( '/profile',        [UserController::class, 'profile']);
$router->post('/profile/update', [UserController::class, 'updateProfile']);

// ─── Administration : Organisations ───────────────────────────────────────────
$router->get( '/admin/organizations',              [OrganizationController::class, 'index']);
$router->get( '/admin/organizations/create',       [OrganizationController::class, 'create']);
$router->post('/admin/organizations',              [OrganizationController::class, 'store']);
$router->get( '/admin/organizations/{id}/edit',    [OrganizationController::class, 'edit']);
$router->post('/admin/organizations/{id}/update',  [OrganizationController::class, 'update']);

// ─── Utilisateurs ─────────────────────────────────────────────────────────────
$router->get( '/users',              [UserController::class, 'index']);
$router->get( '/users/create',       [UserController::class, 'create']);
$router->post('/users',              [UserController::class, 'store']);
$router->get( '/users/{id}/edit',    [UserController::class, 'edit']);
$router->post('/users/{id}/update',  [UserController::class, 'update']);

// ─── Prestations ──────────────────────────────────────────────────────────────
$router->get( '/services',              [ServiceController::class, 'index']);
$router->post('/services',              [ServiceController::class, 'store']);
$router->post('/services/{id}/update',  [ServiceController::class, 'update']);
$router->post('/services/{id}/delete',  [ServiceController::class, 'delete']);

// ─── Campagnes ────────────────────────────────────────────────────────────────
$router->get( '/campaigns',                           [CampaignController::class, 'index']);
$router->get( '/campaigns/create',                    [CampaignController::class, 'create']);
$router->post('/campaigns',                           [CampaignController::class, 'store']);
$router->get( '/campaigns/{id}',                      [CampaignController::class, 'show']);
$router->get( '/campaigns/{id}/edit',                 [CampaignController::class, 'edit']);
$router->post('/campaigns/{id}/update',               [CampaignController::class, 'update']);
$router->post('/campaigns/{id}/start',                [CampaignController::class, 'start']);
$router->post('/campaigns/{id}/close',                [CampaignController::class, 'close']);
$router->post('/campaigns/{id}/duplicate',            [CampaignController::class, 'duplicate']);
$router->get( '/campaigns/{id}/members',              [CampaignController::class, 'members']);
$router->post('/campaigns/{id}/members',              [CampaignController::class, 'addMember']);
$router->post('/campaigns/{cid}/members/{mid}/remove',[CampaignController::class, 'removeMember']);

// ─── Sites ────────────────────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/sites',              [SiteController::class, 'index']);
$router->post('/campaigns/{cid}/sites',              [SiteController::class, 'store']);
$router->post('/campaigns/{cid}/sites/{sid}/update', [SiteController::class, 'update']);
$router->post('/campaigns/{cid}/sites/{sid}/delete', [SiteController::class, 'delete']);

// ─── Locaux ───────────────────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/locations',                          [LocationController::class, 'index']);
$router->get( '/campaigns/{cid}/locations/create',                   [LocationController::class, 'create']);
$router->post('/campaigns/{cid}/locations',                          [LocationController::class, 'store']);
$router->get( '/campaigns/{cid}/locations/{lid}/spreadsheet',        [LocationController::class, 'spreadsheet']);
$router->post('/campaigns/{cid}/locations/{lid}/validate',           [LocationController::class, 'validate']);
$router->post('/campaigns/{cid}/locations/{lid}/reopen',             [LocationController::class, 'reopen']);

// ─── Colonnes dynamiques ──────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/columns',                    [ColumnController::class, 'index']);
$router->post('/campaigns/{cid}/columns',                    [ColumnController::class, 'store']);
$router->post('/campaigns/{cid}/columns/{colid}/update',     [ColumnController::class, 'update']);
$router->post('/campaigns/{cid}/columns/{colid}/delete',     [ColumnController::class, 'delete']);
$router->post('/campaigns/{cid}/columns/reorder',            [ColumnController::class, 'reorder']);

// ─── Rouleaux d'étiquettes ────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/label-rolls',              [LabelRollController::class, 'index']);
$router->post('/campaigns/{cid}/label-rolls',              [LabelRollController::class, 'store']);
$router->post('/campaigns/{cid}/label-rolls/{rid}/skip',   [LabelRollController::class, 'skipCode']);
$router->post('/campaigns/{cid}/label-rolls/{rid}/close',  [LabelRollController::class, 'close']);

// ─── Anomalies ────────────────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/anomalies',         [AnomalyController::class, 'index']);
$router->post('/anomalies/{id}/investigate',        [AnomalyController::class, 'investigate']);
$router->post('/anomalies/{id}/resolve',            [AnomalyController::class, 'resolve']);
$router->post('/anomalies/{id}/reject',             [AnomalyController::class, 'reject']);

// ─── Import ───────────────────────────────────────────────────────────────────
$router->get( '/campaigns/{cid}/import',                    [ImportController::class, 'index']);
$router->post('/campaigns/{cid}/import/upload',             [ImportController::class, 'upload']);
$router->get( '/campaigns/{cid}/import/{jid}/mapping',      [ImportController::class, 'mapping']);
$router->post('/campaigns/{cid}/import/{jid}/preview',      [ImportController::class, 'preview']);
$router->post('/campaigns/{cid}/import/{jid}/execute',      [ImportController::class, 'execute']);
$router->get( '/campaigns/{cid}/import/{jid}/report',       [ImportController::class, 'report']);

// ─── Export ───────────────────────────────────────────────────────────────────
$router->get('/campaigns/{cid}/export', [ExportController::class, 'export']);

// ─── API JSON (tableur) ───────────────────────────────────────────────────────
$router->get(   '/api/campaigns/{cid}/locations/{lid}/items', [InventoryController::class, 'getItems']);
$router->post(  '/api/campaigns/{cid}/locations/{lid}/items', [InventoryController::class, 'createItem']);
$router->post(  '/api/items/{id}/update',                     [InventoryController::class, 'updateItem']);
$router->post(  '/api/items/{id}/delete',                     [InventoryController::class, 'deleteItem']);
$router->post(  '/api/items/{id}/duplicate',                  [InventoryController::class, 'duplicateItem']);
$router->post(  '/api/items/{id}/photos',                     [InventoryController::class, 'uploadPhoto']);
$router->post(  '/api/items/{id}/photos/{photo_id}/delete',   [InventoryController::class, 'deletePhoto']);

// ─── 404 ──────────────────────────────────────────────────────────────────────
$router->setNotFoundHandler(function(\App\Core\Request $request) {
    \App\Core\Response::notFound();
});

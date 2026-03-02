<?php

namespace App\Core;

/**
 * Contrôleur de base
 * Tous les contrôleurs héritent de cette classe
 */
abstract class Controller
{
    /**
     * Rend une vue avec le layout de base
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layout/base'): void
    {
        // Injecter les variables communes
        $data['_user']    = Auth::user();
        $data['_flashes'] = Session::getFlashes();
        $data['_view']    = $view;

        if ($layout) {
            // Capturer le contenu de la vue
            $data['content'] = View::capture($view, $data);
            // Rendre le layout avec le contenu
            View::render($layout, $data);
        } else {
            View::render($view, $data);
        }
    }

    /**
     * Rend une vue sans layout (pour les modales, partials)
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        $data['_user'] = Auth::user();
        View::render($view, $data);
    }

    /**
     * Redirige avec un message flash
     */
    protected function redirectWith(string $url, string $type, string $message): void
    {
        Session::flash($type, $message);
        Response::redirect($url);
    }

    /**
     * Retourne une réponse JSON
     */
    protected function json(mixed $data, int $code = 200): never
    {
        Response::json($data, $code);
    }

    /**
     * Retourne une réponse JSON de succès
     */
    protected function jsonSuccess(mixed $data = null, string $message = 'OK'): never
    {
        Response::jsonSuccess($data, $message);
    }

    /**
     * Retourne une réponse JSON d'erreur
     */
    protected function jsonError(string $message, int $code = 400, array $errors = []): never
    {
        Response::jsonError($message, $code, $errors);
    }

    /**
     * Vérifie le token CSRF et redirige en cas d'échec
     */
    protected function verifyCsrf(): void
    {
        CSRF::verify();
    }

    /**
     * Vérifie l'authentification
     */
    protected function requireAuth(): void
    {
        Auth::require();
    }

    /**
     * Vérifie un rôle minimum
     */
    protected function requireRole(string $role): void
    {
        Auth::requireRole($role);
    }

    /**
     * Vérifie que l'utilisateur est ADMIN
     */
    protected function requireAdmin(): void
    {
        Auth::requireAdmin();
    }

    /**
     * Retourne l'ID de l'organisation courante
     */
    protected function orgId(): ?int
    {
        return Auth::orgId();
    }

    /**
     * Retourne l'utilisateur connecté
     */
    protected function user(): ?array
    {
        return Auth::user();
    }

    /**
     * Paginate une requête
     * Retourne ['items' => [...], 'total' => n, 'page' => n, 'perPage' => n, 'pages' => n]
     */
    protected function paginate(string $sql, array $params, int $perPage = 25): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));

        // Compter le total
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count';
        $total    = (int) Database::fetchScalar($countSql, $params);

        // Appliquer la pagination
        $offset = ($page - 1) * $perPage;
        $items  = Database::fetchAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);

        return [
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int) ceil($total / $perPage),
        ];
    }
}

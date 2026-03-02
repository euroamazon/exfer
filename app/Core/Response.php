<?php

namespace App\Core;

/**
 * Helpers pour les réponses HTTP
 */
class Response
{
    /**
     * Redirige vers une URL
     */
    public static function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirige vers la page précédente (Referer)
     */
    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        self::redirect($referer);
    }

    /**
     * Envoie une réponse JSON
     */
    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Réponse JSON d'erreur standardisée
     */
    public static function jsonError(string $message, int $code = 400, array $errors = []): never
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Réponse JSON de succès standardisée
     */
    public static function jsonSuccess(mixed $data = null, string $message = 'OK'): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * Envoie une réponse texte simple
     */
    public static function text(string $text, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
        exit;
    }

    /**
     * Réponse 403 Forbidden
     */
    public static function forbidden(string $message = 'Accès interdit'): never
    {
        http_response_code(403);
        // Si requête AJAX → JSON
        if (
            (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        ) {
            self::json(['success' => false, 'message' => $message], 403);
        }
        require_once APP_PATH . '/Views/errors/403.php';
        exit;
    }

    /**
     * Réponse 404
     */
    public static function notFound(string $message = 'Page non trouvée'): never
    {
        http_response_code(404);
        if (
            (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        ) {
            self::json(['success' => false, 'message' => $message], 404);
        }
        require_once APP_PATH . '/Views/errors/404.php';
        exit;
    }

    /**
     * Téléchargement de fichier
     */
    public static function download(string $filePath, string $filename, string $mime = 'application/octet-stream'): never
    {
        if (!file_exists($filePath)) {
            self::notFound('Fichier non trouvé');
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($filePath);
        exit;
    }

    /**
     * Stream d'un contenu en téléchargement
     */
    public static function streamDownload(string $content, string $filename, string $mime = 'text/csv'): never
    {
        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $content;
        exit;
    }
}

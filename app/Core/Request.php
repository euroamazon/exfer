<?php

namespace App\Core;

/**
 * Encapsule la requête HTTP entrante
 */
class Request
{
    private array $routeParams = [];

    // ─── Méthode & URI ────────────────────────────────────────────────────────

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || $this->wantsJson();
    }

    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    // ─── Paramètres GET ───────────────────────────────────────────────────────

    public function query(string $key, $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $_GET;
    }

    // ─── Paramètres POST ──────────────────────────────────────────────────────

    public function post(string $key, $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $_POST;
    }

    // ─── Données JSON (requêtes API) ──────────────────────────────────────────

    public function json(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Retourne un champ depuis POST ou JSON selon le Content-Type
     */
    public function input(string $key, $default = null): mixed
    {
        if ($_POST) {
            return $_POST[$key] ?? $default;
        }
        $json = $this->json();
        return $json[$key] ?? $default;
    }

    public function all(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        return $this->json();
    }

    // ─── Paramètres de route ──────────────────────────────────────────────────

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->routeParams;
    }

    // ─── Fichiers ─────────────────────────────────────────────────────────────

    public function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    // ─── En-têtes ─────────────────────────────────────────────────────────────

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    // ─── IP ───────────────────────────────────────────────────────────────────

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    // ─── Validation basique ───────────────────────────────────────────────────

    /**
     * Valide les champs POST selon des règles simples
     * Règles: required, email, min:n, max:n, in:a,b,c, regex:/pattern/
     * Retourne un tableau d'erreurs (vide si valide)
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $data   = $this->all();

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value      = $data[$field] ?? null;
            $label      = $field;

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') {
                    if ($value === null || $value === '') {
                        $errors[$field][] = "Le champ {$label} est obligatoire.";
                        break; // Pas besoin de valider les autres règles
                    }
                } elseif ($rule === 'email') {
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "Le champ {$label} doit être une adresse email valide.";
                    }
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if ($value !== null && strlen((string)$value) < $min) {
                        $errors[$field][] = "Le champ {$label} doit contenir au moins {$min} caractères.";
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if ($value !== null && strlen((string)$value) > $max) {
                        $errors[$field][] = "Le champ {$label} doit contenir au maximum {$max} caractères.";
                    }
                } elseif (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                        $errors[$field][] = "La valeur du champ {$label} est invalide.";
                    }
                } elseif (str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if ($value !== null && $value !== '' && !preg_match($pattern, (string)$value)) {
                        $errors[$field][] = "Le format du champ {$label} est invalide.";
                    }
                } elseif ($rule === 'integer') {
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "Le champ {$label} doit être un nombre entier.";
                    }
                }
            }
        }

        return $errors;
    }
}

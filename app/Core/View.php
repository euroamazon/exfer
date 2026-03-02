<?php

namespace App\Core;

/**
 * Moteur de templates PHP simple
 */
class View
{
    /**
     * Rend une vue PHP avec les données fournies
     *
     * @param string $view  Chemin relatif à /app/Views/, sans extension .php
     *                      Ex: 'campaigns/index', 'layout/base'
     * @param array  $data  Variables à injecter dans la vue
     */
    public static function render(string $view, array $data = []): void
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Vue introuvable : {$file}");
        }

        // Rendre les données accessibles dans la vue
        extract($data, EXTR_SKIP);

        require $file;
    }

    /**
     * Capture le rendu d'une vue dans une chaîne
     */
    public static function capture(string $view, array $data = []): string
    {
        ob_start();
        self::render($view, $data);
        return ob_get_clean();
    }

    /**
     * Échappe une valeur pour l'affichage HTML sécurisé
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Formate une date ISO en format français
     */
    public static function date(?string $date, string $format = 'd/m/Y H:i'): string
    {
        if (!$date) {
            return '—';
        }
        return date($format, strtotime($date));
    }

    /**
     * Badge HTML selon statut
     */
    public static function statusBadge(string $status): string
    {
        $badges = [
            'DRAFT'         => '<span class="badge bg-secondary">Brouillon</span>',
            'ACTIVE'        => '<span class="badge bg-success">Active</span>',
            'CLOSED'        => '<span class="badge bg-dark">Clôturée</span>',
            'IN_PROGRESS'   => '<span class="badge bg-primary">En cours</span>',
            'VALIDATED'     => '<span class="badge bg-success">Validé</span>',
            'NEEDS_REVIEW'  => '<span class="badge bg-warning text-dark">À réviser</span>',
            'OPEN'          => '<span class="badge bg-danger">Ouverte</span>',
            'INVESTIGATION' => '<span class="badge bg-warning text-dark">Investigation</span>',
            'RESOLVED'      => '<span class="badge bg-success">Résolue</span>',
            'REJECTED'      => '<span class="badge bg-secondary">Rejetée</span>',
            'EXHAUSTED'     => '<span class="badge bg-danger">Épuisé</span>',
            'PENDING'       => '<span class="badge bg-secondary">En attente</span>',
            'PROCESSING'    => '<span class="badge bg-primary">En traitement</span>',
            'DONE'          => '<span class="badge bg-success">Terminé</span>',
            'FAILED'        => '<span class="badge bg-danger">Échoué</span>',
        ];
        return $badges[$status] ?? '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}

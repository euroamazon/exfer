<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton PDO — Connexion à la base de données
 *
 * Utilisation :
 *   $db = Database::getInstance();
 *   $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
 *   $stmt->execute([$id]);
 *   $user = $stmt->fetch();
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Retourne l'instance PDO (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    /**
     * Réinitialise la connexion (utile pour les tests)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Crée une connexion PDO à partir de la configuration
     */
    private static function connect(): PDO
    {
        if (!defined('DB_HOST')) {
            throw new \RuntimeException('La configuration de la base de données est manquante. Veuillez lancer /install.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            // Ne pas exposer les détails de la connexion
            throw new \RuntimeException('Impossible de se connecter à la base de données. Vérifiez la configuration.');
        }
    }

    /**
     * Helper : exécute une requête avec des paramètres et retourne le statement
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $db   = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Helper : retourne toutes les lignes
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Helper : retourne une seule ligne
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Helper : retourne une valeur scalaire
     */
    public static function fetchScalar(string $sql, array $params = []): mixed
    {
        $result = self::query($sql, $params)->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Helper : INSERT et retourne lastInsertId
     */
    public static function insert(string $table, array $data): int|string
    {
        $db      = self::getInstance();
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql     = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt    = $db->prepare($sql);
        $stmt->execute(array_values($data));
        return $db->lastInsertId();
    }

    /**
     * Helper : UPDATE
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set       = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $whereSql  = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($where)));
        $sql       = "UPDATE {$table} SET {$set} WHERE {$whereSql}";
        $params    = array_merge(array_values($data), array_values($where));
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Commence une transaction
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * Annule une transaction
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }

    /**
     * Retourne le dernier ID inséré
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
}

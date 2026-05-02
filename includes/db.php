<?php
/**
 * includes/db.php
 * PDO database wrapper — singleton pattern.
 * All queries across the application go through this class.
 */

class Database
{
    /** @var PDO|null Shared PDO instance */
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO connection.
     * Creates the connection on first call.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            if (!defined('DB_HOST')) {
                require_once BASE_PATH . '/config.php';
            }

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log error but never expose credentials in output
                error_log('TokenMed DB Connection Error: ' . $e->getMessage());
                // During development show a friendly message; in production remove the detail
                http_response_code(500);
                die(json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Please contact the administrator.',
                    'data'    => []
                ]));
            }
        }

        return self::$instance;
    }

    /**
     * Convenience: prepare and execute in one call.
     *
     * @param  string $sql    SQL query with ? or :name placeholders
     * @param  array  $params Bound parameters
     * @return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     *
     * @return array|null  Associative array or null if not found
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all rows.
     *
     * @return array[]
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Execute an INSERT and return the last inserted ID.
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Execute an UPDATE / DELETE and return affected rows.
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }

    /** Prevent instantiation — use static methods only */
    private function __construct() {}
    private function __clone() {}
}

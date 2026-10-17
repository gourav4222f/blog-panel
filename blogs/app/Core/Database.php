<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. SQL may reference tables as {{name}}; they are expanded to the
 * prefixed, quoted table name. All values must be passed as bound parameters.
 */
final class Database
{
    private PDO $pdo;
    private string $prefix;
    private int $txDepth = 0;

    public function __construct(array $config)
    {
        $this->prefix = (string) ($config['prefix'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]*$/', $this->prefix)) {
            throw new \InvalidArgumentException('Invalid table prefix.');
        }
        $this->pdo = self::connect($config);
    }

    public static function connect(array $c): PDO
    {
        if (!empty($c['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c['socket'], $c['name']);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'] ?? 'localhost', (int) ($c['port'] ?? 3306), $c['name']);
        }
        $pdo = new PDO($dsn, (string) $c['user'], (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // Never allow several statements in one query string.
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
        return $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        return '`' . $this->prefix . $name . '`';
    }

    public function expand(string $sql): string
    {
        $sql = str_replace('{{prefix}}', $this->prefix, $sql);
        return (string) preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/', fn ($m) => $this->table($m[1]), $sql);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->expand($sql));
        foreach (array_values(array_is_list($params) ? $params : []) as $i => $value) {
            $stmt->bindValue($i + 1, ...self::typed($value));
        }
        if (!array_is_list($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . ltrim((string) $key, ':'), ...self::typed($value));
            }
        }
        $stmt->execute();
        return $stmt;
    }

    private static function typed(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [(int) $value, PDO::PARAM_INT],
            is_int($value) => [$value, PDO::PARAM_INT],
            default => [(string) $value, PDO::PARAM_STR],
        };
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function column(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            implode(', ', array_map([self::class, 'ident'], $cols)),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(', ', array_map(fn ($c) => self::ident($c) . ' = ?', array_keys($data)));
        $stmt = $this->query(sprintf('UPDATE %s SET %s WHERE %s', $this->table($table), $set, $where), [...array_values($data), ...$params]);
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', $this->table($table), $where), $params)->rowCount();
    }

    /** Builds "?, ?, ?" for IN clauses. */
    public static function placeholders(array $values): string
    {
        return $values ? implode(', ', array_fill(0, count($values), '?')) : 'NULL';
    }

    public static function ident(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier.');
        }
        return '`' . $name . '`';
    }

    /**
     * Runs the callback inside a transaction. Nested calls join the outer transaction.
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->txDepth > 0) {
            $this->txDepth++;
            try {
                return $callback($this);
            } finally {
                $this->txDepth--;
            }
        }
        $this->pdo->beginTransaction();
        $this->txDepth = 1;
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->txDepth = 0;
        }
    }

    public function serverVersion(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
}

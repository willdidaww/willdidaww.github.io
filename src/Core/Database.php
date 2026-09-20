<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Wrapper PDO tipis dengan helper query yang aman (selalu prepared statement).
 * Kompatibel SQLite (default) & PostgreSQL/MySQL (cukup ganti DSN).
 */
final class Database
{
    private PDO $pdo;
    private bool $isSqlite;

    public function __construct(string $dsn, ?string $user = null, ?string $pass = null)
    {
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->isSqlite = str_starts_with($dsn, 'sqlite:');
        if ($this->isSqlite) {
            // Integritas & konkurensi untuk SQLite
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public function pdo(): PDO { return $this->pdo; }
    public function isSqlite(): bool { return $this->isSqlite; }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Ambil satu baris (atau null). */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Ambil banyak baris. */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Ambil satu nilai scalar. */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** INSERT helper; kembalikan last insert id (int). */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $place = array_map(static fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', $place)
        );
        $this->run($sql, $this->prefix($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE helper berdasar kondisi where (array kolom=>nilai). */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn($c) => "$c = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn($c) => "$c = :w_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v)  { $params[":set_$k"] = $v; }
        foreach ($where as $k => $v) { $params[":w_$k"] = $v; }
        return $this->run("UPDATE $table SET $set WHERE $cond", $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(static fn($c) => "$c = :$c", array_keys($where)));
        return $this->run("DELETE FROM $table WHERE $cond", $this->prefix($where))->rowCount();
    }

    public function begin(): void  { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }

    /** Jalankan closure dalam transaksi; auto commit/rollback. */
    public function transaction(callable $fn): mixed
    {
        $this->begin();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function prefix(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[':' . $k] = $v;
        }
        return $out;
    }
}

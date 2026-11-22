<?php

declare(strict_types=1);

namespace Cans;

use PDO;
use PDOStatement;

/** Lazily-connected PDO wrapper. Prepared statements only; UTC session. */
final class Db
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO(
                (string) $this->config->get('db.dsn'),
                (string) $this->config->get('db.user'),
                (string) $this->config->get('db.password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
            $this->pdo->exec("SET NAMES utf8mb4, time_zone = '+00:00'");
        }

        return $this->pdo;
    }

    /** @param array<string|int, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}

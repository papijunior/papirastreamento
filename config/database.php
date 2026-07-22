<?php

declare(strict_types=1);

final class Database
{
    // 127.0.0.1 força TCP e evita conflito de socket entre PHP do sistema e MySQL do XAMPP
    private string $host = '127.0.0.1';
    private string $port = '3306';
    private string $database = 'papi_rastro_db';
    private string $username = 'root';
    private string $password = '';
    private ?PDO $connection = null;

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->ensureDatabaseExists();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Não foi possível conectar ao banco de dados MySQL (papi_rastro_db).',
                0,
                $exception
            );
        }

        return $this->connection;
    }

    private function ensureDatabaseExists(): void
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $this->host, $this->port);

        try {
            $pdo = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $this->database . '`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Não foi possível criar/conectar ao MySQL. Verifique se o XAMPP MySQL está em execução.',
                0,
                $exception
            );
        }
    }
}

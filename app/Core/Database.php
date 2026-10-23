<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Accès MySQL/MariaDB. Toujours des requêtes préparées ; les Repositories sont les seuls à écrire du SQL.
 */
final class Database
{
    private static ?self $instance = null;

    private function __construct(private readonly PDO $pdo)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOTE', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NOM', 'allomecano')
            );
            $pdo = new PDO($dsn, (string) Env::get('DB_UTILISATEUR', 'root'), (string) Env::get('DB_MOT_DE_PASSE', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            // Abidjan est en GMT toute l'année : on stocke et on lit en UTC.
            $pdo->exec("SET time_zone = '+00:00'");
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string|int, mixed> $params */
    public function executer(string $sql, array $params = []): PDOStatement
    {
        $requete = $this->pdo->prepare($sql);
        foreach ($params as $cle => $valeur) {
            $nom = is_int($cle) ? $cle + 1 : (str_starts_with($cle, ':') ? $cle : ':' . $cle);
            $type = match (true) {
                is_int($valeur) => PDO::PARAM_INT,
                is_bool($valeur) => PDO::PARAM_BOOL,
                $valeur === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $requete->bindValue($nom, $valeur, $type);
        }
        $requete->execute();
        return $requete;
    }

    /** @param array<string|int, mixed> $params @return array<string, mixed>|null */
    public function un(string $sql, array $params = []): ?array
    {
        $ligne = $this->executer($sql, $params)->fetch();
        return $ligne === false ? null : $ligne;
    }

    /** @param array<string|int, mixed> $params @return list<array<string, mixed>> */
    public function tous(string $sql, array $params = []): array
    {
        return $this->executer($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function valeur(string $sql, array $params = []): mixed
    {
        $valeur = $this->executer($sql, $params)->fetchColumn();
        return $valeur === false ? null : $valeur;
    }

    /** @param array<string, mixed> $donnees */
    public function inserer(string $table, array $donnees): int
    {
        $colonnes = array_keys($donnees);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $colonnes),
            implode(', ', array_map(fn ($c) => ':' . $c, $colonnes))
        );
        $this->executer($sql, $donnees);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $donnees @param array<string, mixed> $ou */
    public function modifier(string $table, array $donnees, array $ou): int
    {
        $set = implode(', ', array_map(fn ($c) => "`$c` = :s_$c", array_keys($donnees)));
        $where = implode(' AND ', array_map(fn ($c) => "`$c` = :w_$c", array_keys($ou)));
        $params = [];
        foreach ($donnees as $c => $v) {
            $params['s_' . $c] = $v;
        }
        foreach ($ou as $c => $v) {
            $params['w_' . $c] = $v;
        }
        return $this->executer("UPDATE `$table` SET $set WHERE $where", $params)->rowCount();
    }

    /**
     * @template T
     * @param callable(self): T $travail
     * @return T
     */
    public function transaction(callable $travail): mixed
    {
        // Déjà dans une transaction (appel imbriqué, tests) : le travail en fait partie
        if ($this->pdo->inTransaction()) {
            return $travail($this);
        }
        $this->pdo->beginTransaction();
        try {
            $resultat = $travail($this);
            $this->pdo->commit();
            return $resultat;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

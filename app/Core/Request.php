<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    public array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $serveur
     * @param array<string, mixed> $fichiers
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public readonly string $methode,
        public readonly string $chemin,
        private readonly array $query,
        private readonly array $post,
        private readonly array $serveur,
        private readonly array $fichiers,
        private readonly string $corps = '',
        private readonly array $cookies = [],
    ) {
    }

    public static function depuisGlobales(): self
    {
        $chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $chemin = '/' . trim(rawurldecode($chemin), '/');
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        // Formulaires HTML : PUT / DELETE via le champ _methode
        if ($methode === 'POST' && isset($_POST['_methode'])) {
            $methode = strtoupper((string) $_POST['_methode']);
        }
        return new self($methode, $chemin, $_GET, $_POST, $_SERVER, $_FILES, (string) file_get_contents('php://input'), $_COOKIE);
    }

    public function query(string $cle, mixed $defaut = null): mixed
    {
        return $this->query[$cle] ?? $defaut;
    }

    public function input(string $cle, mixed $defaut = null): mixed
    {
        return $this->post[$cle] ?? $this->json()[$cle] ?? $defaut;
    }

    /** @return array<string, mixed> */
    public function tout(): array
    {
        return array_merge($this->query, $this->post, $this->json());
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        static $cache = [];
        if (!str_contains($this->entete('Content-Type') ?? '', 'application/json') || $this->corps === '') {
            return [];
        }
        return $cache[spl_object_id($this)] ??= (array) (json_decode($this->corps, true) ?? []);
    }

    /** @return array<string, mixed>|null */
    public function fichier(string $cle): ?array
    {
        $f = $this->fichiers[$cle] ?? null;
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    public function cookie(string $nom): ?string
    {
        $v = $this->cookies[$nom] ?? null;
        return is_string($v) ? $v : null;
    }

    public function entete(string $nom): ?string
    {
        $cle = 'HTTP_' . strtoupper(str_replace('-', '_', $nom));
        if ($nom === 'Content-Type') {
            $cle = 'CONTENT_TYPE';
        }
        $v = $this->serveur[$cle] ?? null;
        return $v === null ? null : (string) $v;
    }

    public function veutJson(): bool
    {
        return str_starts_with($this->chemin, '/api/') || str_contains($this->entete('Accept') ?? '', 'application/json');
    }

    public function ip(): string
    {
        return (string) ($this->serveur['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function param(string $cle): ?string
    {
        return $this->params[$cle] ?? null;
    }
}

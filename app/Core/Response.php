<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string, string> $entetes */
    public function __construct(
        public string $corps = '',
        public int $statut = 200,
        public array $entetes = [],
    ) {
    }

    public static function html(string $html, int $statut = 200): self
    {
        return new self($html, $statut, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<mixed>|object $donnees */
    public static function json(array|object $donnees, int $statut = 200): self
    {
        return new self(
            (string) json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $statut,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }

    public static function redirection(string $url, int $statut = 303): self
    {
        return new self('', $statut, ['Location' => $url]);
    }

    public function avecEntete(string $nom, string $valeur): self
    {
        $this->entetes[$nom] = $valeur;
        return $this;
    }

    public function envoyer(): void
    {
        http_response_code($this->statut);
        $securite = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            // micro autorisé pour l'appel intégré, localisation pour la recherche
            'Permissions-Policy' => 'microphone=(self), geolocation=(self), camera=(self)',
        ];
        foreach ($securite + $this->entetes as $nom => $valeur) {
            header($nom . ': ' . $valeur);
        }
        echo $this->corps;
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $statut, string $message = '')
    {
        parent::__construct($message, $statut);
    }

    public static function introuvable(string $message = 'Page introuvable'): self
    {
        return new self(404, $message);
    }

    public static function interdit(string $message = 'Accès refusé'): self
    {
        return new self(403, $message);
    }
}

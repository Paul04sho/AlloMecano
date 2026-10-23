<?php

declare(strict_types=1);

namespace App\Services;

/** Un contact refusé : le code dit pourquoi (indisponible, limite, canal, issue), le message est montré au client. */
final class ContactException extends \RuntimeException
{
    public function __construct(public readonly string $code_refus, string $message)
    {
        parent::__construct($message);
    }
}

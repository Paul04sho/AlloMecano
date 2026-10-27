<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Format;
use App\Core\View;

/** Échappement HTML : à utiliser pour TOUTE donnée affichée. */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $chemin = '/'): string
{
    return rtrim((string) Env::get('APP_URL', ''), '/') . '/' . ltrim($chemin, '/');
}

/** URL d'un fichier de public/assets avec empreinte de version (cache long côté navigateur). */
function asset(string $chemin): string
{
    $fichier = __DIR__ . '/../public/assets/' . ltrim($chemin, '/');
    $version = is_file($fichier) ? substr(md5((string) filemtime($fichier)), 0, 8) : '0';
    return '/assets/' . ltrim($chemin, '/') . '?v=' . $version;
}

/** Icône du sprite (scripts/icones.json). Taille 24 par défaut ; les voyants sont en 32, les véhicules en 40. */
function icone(string $nom, int $taille = 24, string $classe = ''): string
{
    return sprintf(
        '<svg class="icone %s" width="%d" height="%d" aria-hidden="true" focusable="false"><use href="%s#i-%s"></use></svg>',
        e($classe), $taille, $taille, e(asset('icones/sprite.svg')), e($nom)
    );
}

/** Composant du design system (app/Views/composants). */
function composant(string $nom, array $props = []): string
{
    return View::inclure('composants/' . $nom, $props);
}

function csrf_champ(): string
{
    return Csrf::champ();
}

/** « 1,2 » + unité KM en Mono : la Borne de la spec (S6). */
function borne(string $valeur, string $unite, string $taille = 'l'): string
{
    return '<span class="borne borne--' . e($taille) . '"><span class="borne__valeur">' . e($valeur) . '</span><span class="borne__unite">' . e($unite) . '</span></span>';
}

function fr_typo(string $texte): string
{
    return Format::typo($texte);
}

/** Déclarations CSS qui font voyager le portrait d'un mécano d'un écran à l'autre (transition-vue.css). Vide sans slug. */
function vue_portrait(?string $slug): string
{
    $nom = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $slug));
    return $nom === '' ? '' : 'view-transition-name:portrait-' . $nom . ';view-transition-class:portrait';
}

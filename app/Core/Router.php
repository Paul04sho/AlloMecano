<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : chemins avec paramètres {nom}, groupes avec préfixe et middlewares.
 * Un middleware est une classe avec une méthode traiter(Request $r, callable $suite): Response.
 */
final class Router
{
    /** @var list<array{methode: string, motif: string, regex: string, action: array{0: class-string, 1: string}|callable, middlewares: list<class-string>, nom: ?string}> */
    private array $routes = [];
    private string $prefixe = '';
    /** @var list<class-string> */
    private array $middlewares = [];
    /** @var array<string, string> */
    private array $noms = [];

    /** @param array{0: class-string, 1: string}|callable $action */
    public function get(string $motif, array|callable $action, ?string $nom = null): self
    {
        return $this->ajouter('GET', $motif, $action, $nom);
    }

    /** @param array{0: class-string, 1: string}|callable $action */
    public function post(string $motif, array|callable $action, ?string $nom = null): self
    {
        return $this->ajouter('POST', $motif, $action, $nom);
    }

    /** @param list<class-string> $middlewares */
    public function groupe(string $prefixe, array $middlewares, callable $definition): void
    {
        [$p, $m] = [$this->prefixe, $this->middlewares];
        $this->prefixe .= $prefixe;
        $this->middlewares = [...$this->middlewares, ...$middlewares];
        $definition($this);
        [$this->prefixe, $this->middlewares] = [$p, $m];
    }

    /** @param array{0: class-string, 1: string}|callable $action */
    private function ajouter(string $methode, string $motif, array|callable $action, ?string $nom): self
    {
        $motif = '/' . trim($this->prefixe . $motif, '/');
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $motif) . '$#u';
        $this->routes[] = ['methode' => $methode, 'motif' => $motif, 'regex' => $regex, 'action' => $action, 'middlewares' => $this->middlewares, 'nom' => $nom];
        if ($nom !== null) {
            $this->noms[$nom] = $motif;
        }
        return $this;
    }

    /** @param array<string, string|int> $params */
    public function url(string $nom, array $params = []): string
    {
        $motif = $this->noms[$nom] ?? throw new \InvalidArgumentException("Route inconnue : $nom");
        return preg_replace_callback('#\{([a-z_]+)\}#', fn ($m) => rawurlencode((string) ($params[$m[1]] ?? '')), $motif) ?? $motif;
    }

    public function dispatcher(Request $requete): Response
    {
        $methodeAutorisee = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $requete->chemin, $m)) {
                continue;
            }
            $methodeAutorisee = true;
            $methode = $requete->methode === 'HEAD' ? 'GET' : $requete->methode;
            if ($route['methode'] !== $methode) {
                continue;
            }
            $requete->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $coeur = function (Request $r) use ($route): Response {
                $action = $route['action'];
                if (is_array($action)) {
                    [$classe, $fonction] = $action;
                    $action = [new $classe(), $fonction];
                }
                $resultat = $action($r);
                return $resultat instanceof Response ? $resultat : Response::html((string) $resultat);
            };
            $chaine = array_reduce(
                array_reverse($route['middlewares']),
                fn (callable $suite, string $mw) => fn (Request $r) => (new $mw())->traiter($r, $suite),
                $coeur
            );
            return $chaine($requete);
        }
        throw new HttpException($methodeAutorisee ? 405 : 404);
    }
}

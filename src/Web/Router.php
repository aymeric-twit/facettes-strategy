<?php

declare(strict_types=1);

namespace Facettes\Web;

/**
 * Routeur HTTP minimaliste avec support GET/POST et paramètres nommés.
 */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes['GET'][$pattern] = $handler;
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes['POST'][$pattern] = $handler;
    }

    public function resoudre(string $methode, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/') ?: '/';
        $methode = strtoupper($methode);

        if (!isset($this->routes[$methode])) {
            $this->repondre404();
            return;
        }

        foreach ($this->routes[$methode] as $pattern => $handler) {
            $regex = $this->patternVersRegex($pattern);

            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...$params);
                return;
            }
        }

        $this->repondre404();
    }

    private function patternVersRegex(string $pattern): string
    {
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function repondre404(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => 'Route non trouvée'], JSON_UNESCAPED_UNICODE);
    }
}

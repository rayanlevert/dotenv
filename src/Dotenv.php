<?php

namespace DisDev\Dotenv;

/**
 * Class simple qui gère le fichier env et charge son contenu dans $_ENV, $_SERVER et getenv
 */
class Dotenv
{
    /**
     * Set le chemin du fichier .env, vérifie qu'il est bien readable
     *
     * @throws \DisDev\Dotenv\Exception Si le fichier n'existe pas, n'est pas readable ou est empty
     */
    public function __construct(protected string $filePath)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new Exception($filePath . ' n\'existe pas ou est empty');
        }
    }

    /**
     * Lit le contenu du fichier et charge $_ENV et $_SERVER des variables d'environnement
     *
     * @throws Exception Si une valeur possède un " après le = et qu'un " fermant n'a pas été trouvé
     */
    public function load(): self
    {
        if (empty($contents = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
            return $this;
        }

        foreach ($contents as $numberLine => $line) {
            if (substr($line, 0, 1) === '#') {
                continue;
            }

            // Si il y a un espace + # après la déclaration de la variable, on enlève la partie doc
            if ($pos = mb_strpos($line, ' #')) {
                $line = trim(substr_replace($line, '', $pos));
            }

            // Si il n'y a pas d'= (ou plusieurs = => on prend la valeur après le deuxième =)
            if (count($exploded = explode('=', $line, 2)) !== 2) {
                continue;
            }

            // Si une double quote est trouvé, on recherche le prochain pour fermer la valeur
            if (str_starts_with($exploded[1], '"')) {
                $exploded[1] = $this->handleDoubleQuotes($exploded, $numberLine, $contents);
            }

            list($name, $value) = $exploded;

            if (isset($_SERVER[$name]) && isset($_ENV[$name])) {
                continue;
            }

            // Si la valeur est un nombre, on la cast en int ou en float, si false ou true on cast en bool
            if (is_numeric($value)) {
                $value = (strpos($value, '.') ? (float) $value : (int) $value);
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($value === 'true') {
                $value = true;
            }

            putenv("$name=$value");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        return $this;
    }

    /**
     * Vérifie que les variables d'env passées en paramètre existent bien dans $_ENV
     *
     * @param array<int, string> $envs Array indéxé des variables d'env required
     *
     * @throws \DisDev\Dotenv\Exception Si au moins une seule variable n'est pas présente
     */
    public function required(array $envs): void
    {
        foreach ($envs as $key => $env) {
            if (isset($_ENV[$env])) {
                unset($envs[$key]);
            }
        }

        if (!empty($envs)) {
            throw new Exception('Missing env variables : ' . implode(', ', $envs));
        }
    }

    /**
     * Dès qu'un double quote est trouvé, cherche le deuxième pour établir le string
     *
     * @param array{0: string, 1: string} $exploded Array explodé de la ligne (nom=valeur)
     * @param int $currentLine Ligne à laquelle le double quote a été trouvé
     * @param array<int, string> $contents Contenu du fichier
     *
     * @throws Exception Si le double quote fermant n'a pas été trouvé
     */
    private function handleDoubleQuotes(array $exploded, int $currentLine, array $contents): string
    {
        $value = substr($exploded[1], 1);

        if (str_ends_with($value, '"')) {
            return substr($value, 0, -1);
        }

        foreach (array_slice($contents, $currentLine + 1) as $line) {
            if (mb_strpos($line, '"')) {
                return $value .= PHP_EOL . substr($line, 0, -1);
            }

            $value .= "\n$line";
        }

        throw new Exception("Une variable a une double quote (\") qui ne se ferme pas, variable: {$exploded[0]}");
    }
}

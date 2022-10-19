<?php

namespace DisDev\Dotenv;

/**
 * Class simple qui gère le fichier env et charge son contenu dans $_ENV, $_SERVER et getenv
 */
class Dotenv
{
    protected string $filePath;

    /**
     * Set le chemin du fichier .env, vérifie qu'il est bien readable
     *
     * @throws \DisDev\Dotenv\Dotenv Si le fichier n'existe pas, n'est pas readable ou est empty
     */
    public function __construct(string $filePath)
    {
        if (!is_file($filePath) || !is_readable($filePath) || !file_get_contents($filePath)) {
            throw new Exception($filePath . ' n\'existe pas ou est empty');
        }

        $this->filePath = $filePath;
    }

    /**
     * Lit le contenu du fichier et charge $_ENV et $_SERVER des variables d'environnement
     */
    public function load(): self
    {
        if (empty($contents = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
            return $this;
        }

        foreach ($contents as $line) {
            if (substr($line, 0, 1) === '#') {
                continue;
            }

            // Si il y a un # après la déclaration de la variable, on enlève la partie doc
            if ($pos = mb_strpos($line, '#')) {
                $line = trim(substr_replace($line, '', $pos));
            }

            // Si il n'y a pas d'= (ou plusieurs = => on prend la valeur après le deuxième =)
            if (count($exploded = explode('=', $line, 2)) !== 2) {
                continue;
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
     * @throws \DisDev\Dotenv\Dotenv Si au moins une seule variable n'est pas présente
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
}

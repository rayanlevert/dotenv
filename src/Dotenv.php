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

        $oIterator = new \ArrayIterator($contents);

        foreach ($oIterator as $numberLine => $line) {
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

            if (str_starts_with($exploded[1], '${')) {
                $this->handleNestedVariable($exploded);
            }

            /**
             * Si une double quote est trouvée, on recherche la prochaine pour fermer la valeur
             * Si plusieurs lines on été traitées, les skip pour ne pas les retraiter dans les prochaines itérations
             */
            if (str_starts_with($exploded[1], '"')) {
                $oIterator->seek($numberLine + $this->handleDoubleQuotes($exploded, $numberLine, $contents));
            }

            list($name, $value) = $exploded;

            if (array_key_exists($name, $_SERVER) && array_key_exists($name, $_ENV)) {
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
     * Si la valeur commence par ${ c'est que la variable utilise une variable nested -> essaie de la récupérer
     * et remplace par la valeur de cette variable importée
     *
     * @param array{0: string, 1: string} $exploded Array explodé de la ligne (nom, valeur)
     */
    private function handleNestedVariable(array &$exploded): void
    {
        if (!$pos = mb_strrpos($exploded[1], '}')) {
            throw new Exception("Variable {$exploded[0]} utilise une variable nested non fermée (\${})");
        }

        $nestedName = mb_substr($exploded[1], 2, $pos - 2);

        if (array_key_exists($nestedName, $_ENV)) {
            $this->replaceNested($nestedName, $_ENV[$nestedName], $exploded[1]);
        } elseif (array_key_exists($nestedName, $_SERVER)) {
            $this->replaceNested($nestedName, $_SERVER[$nestedName], $exploded[1]);
        } elseif ($value = getenv($nestedName)) {
            $this->replaceNested($nestedName, $value, $exploded[1]);
        } else {
            throw new Exception("Variable d'env nested $nestedName non trouvée par PHP");
        }
    }

    /**
     * Remove le dollar et l'accolade ouvrante et fermante (`${}`) par la valeur de la variable nested
     */
    private function replaceNested(string $name, string $value, string &$line): void
    {
        $line = str_replace(sprintf('${%s}', $name), $value, $line);
    }

    /**
     * Dès qu'un double quote est trouvé, cherche le deuxième pour établir le string
     *
     * @param array{0: string, 1: string} $exploded Array explodé de la ligne (nom, valeur)
     * @param int $currentLine Ligne à laquelle le double quote a été trouvé
     * @param array<int, string> $contents Contenu du fichier
     *
     * @throws Exception Si le double quote fermant n'a pas été trouvé
     *
     * @return int Nombre de lignes que la variable à double quote possède
     */
    private function handleDoubleQuotes(array &$exploded, int $currentLine, array $contents): int
    {
        $exploded[1] = substr($exploded[1], 1);

        // Si le double quote est sur la même ligne -> nul besoin de traverser les lignes suivantes
        if (str_ends_with($exploded[1], '"')) {
            $exploded[1] = substr($exploded[1], 0, -1);

            return 0;
        }

        $lines = 0;

        // On boucle dans chaque ligne après la currente et concatène la valeur jusque le double quote trouvé
        foreach (array_slice($contents, $currentLine + 1) as $line) {
            $lines++;

            if (mb_strpos($line, '"')) {
                $exploded[1] .= PHP_EOL . substr($line, 0, -1);

                return $lines;
            }

            $exploded[1] .= "\n$line";
        }

        throw new Exception("Une variable a une double quote (\") qui ne se ferme pas, variable: {$exploded[0]}");
    }
}

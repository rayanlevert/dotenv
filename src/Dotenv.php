<?php

namespace RayanLevert\Dotenv;

/**
 * Simple and fast class handling an environment file to `$_ENV`, `$_SERVER` and `getenv()`
 */
class Dotenv
{
    /**
     * Initializes the instance setting the file path
     *
     * @throws \RayanLevert\Dotenv\Exception If the file is not readable
     */
    public function __construct(protected string $filePath)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new Exception("Environment file $filePath is not readable");
        }
    }

    /**
     * Reads the file content and loads in the superglobals, values of each variable
     *
     * @throws Exception If a variable doesn't end its value with a `"`
     * @throws Exception If an used nested variable is not known
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

            // If a space + # is found -> we remove the documentation part
            if ($pos = mb_strpos($line, ' #')) {
                $line = trim(substr_replace($line, '', $pos));
            }

            // If no = exists or multiple ones are found -> we get the first one
            if (count($exploded = explode('=', $line, 2)) !== 2) {
                continue;
            }

            /**
             * Double quote found -> we found the next closing double quote
             * If multiple lines have been handled -> we skip those for the next iteration
             */
            if (str_starts_with($exploded[1], '"')) {
                $oIterator->seek($numberLine + $this->handleDoubleQuotes($exploded, $numberLine, $contents));
            }

            // Use of a nested variable '${}'
            if (str_contains($exploded[1], '${')) {
                $this->handleNestedVariables($exploded);
            }

            list($name, $value) = $exploded;

            if (array_key_exists($name, $_SERVER) && array_key_exists($name, $_ENV)) {
                continue;
            }

            /**
             * Value is a number -> integer or float casting
             * Value is a boolean -> boolean casting
             */
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
     * Verifies environment variables from the argument exist in `$_ENV`
     *
     * @param array<int, string> $envs Indexed array of required environement variables
     *
     * @throws \RayanLevert\Dotenv\Exception If at least one variable is not present
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
     * If a value starts with ${ -> use of a nested variable, we try to retrieve its value and replace it
     *
     * @param array{0: string, 1: string} $exploded Exploded array explodé of the line (name, value)
     *
     * @throws Exception If a nested variable is not retrieved
     */
    private function handleNestedVariables(array &$exploded): void
    {
        $exploded[1] = preg_replace_callback('/\${([a-zA-Z0-9_.]+)}/', function (array $aMatches): string {
            $nestedName = $aMatches[1];

            return match (true) {
                array_key_exists($nestedName, $_ENV)    => $_ENV[$nestedName],
                array_key_exists($nestedName, $_SERVER) => $_SERVER[$nestedName],
                getenv($nestedName) !== false           => getenv($nestedName),
                default => throw new Exception("Nested environment variable $nestedName not found")
            };
        }, $exploded[1]);
    }

    /**
     * For double quotes found, we find the closing one to get the full value
     *
     * @param array{0: string, 1: string} $exploded Exploded array of the line (name, value)
     * @param int $currentLine Number of the retrieved line
     * @param array<int, string> $contents File contents
     *
     * @throws Exception If the closing quote has not been found
     *
     * @return int Line number the value's variable has
     */
    private function handleDoubleQuotes(array &$exploded, int $currentLine, array $contents): int
    {
        $exploded[1] = substr($exploded[1], 1);

        // If the doubloe quote is on the same line -> no need to loop
        if (str_ends_with($exploded[1], '"')) {
            $exploded[1] = substr($exploded[1], 0, -1);

            return 0;
        }

        $lines = 0;

        // We loop after each line to retrieve a closing quote
        foreach (array_slice($contents, $currentLine + 1) as $line) {
            $lines++;

            if (mb_strpos($line, '"')) {
                $exploded[1] .= PHP_EOL . substr($line, 0, -1);

                return $lines;
            }

            $exploded[1] .= "\n$line";
        }

        throw new Exception("Environment variable has a double quote (\") not closing in, variable: {$exploded[0]}");
    }
}

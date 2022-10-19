<?php

namespace DisDev\Dotenv\Tests;

use DisDev\Dotenv\Dotenv;
use DisDev\Dotenv\Exception;

class DotenvTest extends \PHPUnit\Framework\TestCase
{
    protected array $filesToDelete = [];

    /**
     * @test Test le fichier d'un string empty
     */
    public function testConstructorEmptyString(): void
    {
        $this->expectExceptionObject(new Exception(' n\'existe pas ou est empty'));

        new Dotenv('');
    }

    /**
     * @test Test le fichier d'un string qui n'est pas un fichier
     */
    public function testConstructorNotFile(): void
    {
        $this->expectExceptionObject(new Exception(' n\'existe pas ou est empty'));

        new Dotenv('test');
    }

    /**
     * @test Test le fichier d'un string qui n'est pas un fichier
     */
    public function testConstructorEmptyFile(): void
    {
        $this->expectExceptionObject(new Exception(' n\'existe pas ou est empty'));

        $this->createFile('/app/data/.env');

        new Dotenv('/app/data/.env');
    }

    /**
     * @test Test le fichier d'un string qui est bien un file
     */
    public function testConstructorCorrectFile(): void
    {
        $this->createFile('/app/data/.env', 'test=test');

        $this->assertInstanceOf(Dotenv::class, new Dotenv('/app/data/.env'));
    }

    /**
     * @test Test le fichier ayant un diez au début
     */
    public function testFileWithHashDebut(): void
    {
        $this->createFile('/app/data/.env', "# TEST=test");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertArrayNotHasKey('TEST', $_ENV);
    }

    /**
     * @test Test le fichier ayant un diez dans la déclaration de la variable
     */
    public function testFileWithHashInBetween(): void
    {
        $this->createFile('/app/data/.env', "TEST=test # Variable TEST\n TEST2=te#ST2");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());

        $this->assertVariableIsHandled('TEST', 'test');
        $this->assertVariableIsHandled('TEST2', 'te');
    }

    /**
     * @test Test le fichier avec un ligne sans egal = pas pris en compte
     */
    public function testFileWithNoEqual(): void
    {
        $this->createFile('/app/data/.env', "TEST\nTEST2=correct");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());

        $this->assertArrayNotHasKey('TEST', $_ENV);
        $this->assertVariableIsHandled('TEST2', 'correct');
    }

    /**
     * @test Test le fichier avec variable qui possède plusieurs égals
     */
    public function testFileWithMoreThan2Equals(): void
    {
        $this->createFile('/app/data/.env', "TEST=test=te\nTE=t=e=s=t");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());

        $this->assertVariableIsHandled('TEST', 'test=te');
        $this->assertVariableIsHandled('TE', 't=e=s=t');
    }

    /**
     * @test Test le fichier avec deux fois le même nom de variable = premiere valeur de prise
     */
    public function testFileWithSameVariable(): void
    {
        $this->createFile('/app/data/.env', "TEST=test1\nTEST=test2");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', 'test1');
    }

    /**
     * @test Test une valeur float (avec un point)
     */
    public function testFileWithFloatValue(): void
    {
        $this->createFile('/app/data/.env', 'TEST=34.3');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', 34.3);
    }

    /**
     * @test Test une valeur float (avec une virgule => non prise en compte) donc string
     */
    public function testFileWithFloatValueButComma(): void
    {
        $this->createFile('/app/data/.env', 'TEST=34,3');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', '34,3');
    }

    /**
     * @test Test une valeur integer
     */
    public function testFileWithIntValue(): void
    {
        $this->createFile('/app/data/.env', 'TEST=34');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', 34);
    }

    /**
     * @test Test une valeur true
     */
    public function testFileWithTrueValue(): void
    {
        $this->createFile('/app/data/.env', 'TEST=true');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', true);
    }

    /**
     * @test Test une valeur false
     */
    public function testFileWithFalseValue(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv('/app/data/.env'))->load());
        $this->assertVariableIsHandled('TEST', false);
    }

    /**
     * @test Test un empty $envs en required => pas d'exception
     */
    public function testRequiredEmptyEnvs(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $oDotenv = (new Dotenv('/app/data/.env'))->load();
        $oDotenv->required([]);

        $this->assertInstanceOf(Dotenv::class, $oDotenv);
    }

    /**
     * @test Test une variable non présente
     */
    public function testRequiredOneEnvMissing(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        (new Dotenv('/app/data/.env'))->load()->required(['TESTNOTIN']);
    }

    /**
     * @test Test deux variables non présentes
     */
    public function testRequiredTwoEnvMissing(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN, TESTNOTIN2'));
        (new Dotenv('/app/data/.env'))->load()->required(['TESTNOTIN', 'TESTNOTIN2']);
    }

    /**
     * @test Test une variable de présente et une autre non présente
     */
    public function testRequiredOneInOneNotMissing(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        (new Dotenv('/app/data/.env'))->load()->required(['TESTNOTIN', 'TEST']);
    }

    /**
     * @test Test une variable de présente mais pas la même casse => exception
     */
    public function testRequiredOneCaseSensitive(): void
    {
        $this->createFile('/app/data/.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : test'));
        (new Dotenv('/app/data/.env'))->load()->required(['test']);
    }

    /**
     * @test Test le fichier test.env, sample d'un vrai fichier .env
     */
    public function testSampleTestCsvAllVariables(): void
    {
        (new Dotenv('/app/tests/test.env'))->load();

        $this->assertVariableIsHandled('APP_ENV', 'development');
        $this->assertVariableIsHandled('APP_NAME', '"Nom de l\'application test"'); // les quotes sont gardées
        $this->assertVariableIsHandled('APP_URL', 'https://test.local.fr');
        $this->assertVariableIsHandled('APP_CACHE', '/app/cache');
        $this->assertVariableIsHandled('APP_LOGS', '/app/logs');

        $this->assertVariableIsHandled('WEB_DOCUMENT_ROOT', '/app/public');
        $this->assertVariableIsHandled('WEB_ALIAS_DOMAIN', 'test.local.fr');

        $this->assertVariableIsHandled('MYSQL_ROOT_PASSWORD', 'root');
        $this->assertVariableIsHandled('MYSQL_DATABASE', 'test_base');
        $this->assertVariableIsHandled('MYSQL_USER', 'root');
        $this->assertVariableIsHandled('MYSQL_PASSWORD', 'root');
        $this->assertVariableIsHandled('MYSQL_PORT', 33061);
        $this->assertVariableIsHandled('MYSQL_HOST', 'mysql');

        $this->assertVariableIsHandled('REDIS_HOST', 'redis');
        $this->assertVariableIsHandled('REDIS_PORT', 6379);
        $this->assertVariableIsHandled('REDIS_PASSWORD', ''); // Aucune valeur => empty string
        $this->assertVariableIsHandled('REDIS_INDEX', 0);
        $this->assertVariableIsHandled('REDIS_LIFETIME', 7200);
        $this->assertVariableIsHandled('REDIS_PERSISTENT', true);

        $this->assertVariableIsHandled('SESSION_PERSISTENT', true);
        $this->assertVariableIsHandled('SESSION_LIFETIME', 43200);
        $this->assertVariableIsHandled('SESSION_SECURE', 1);
        $this->assertVariableIsHandled('SESSION_HTTPONLY', 1);

        $this->assertVariableIsHandled('SECURITY_KEY', 'i³}tß¬¿ìò»å7L¼¤<¸%SÔº¶^µ2È]³öXG¹»ï@³î2jÒ8º');
        $this->assertVariableIsHandled('SECURITY_JWT_MIN_TTL', 0);
        $this->assertVariableIsHandled('SECURITY_JWT_MAX_TTL', 3600);

        $this->assertVariableIsHandled('MAILER_DRIVER', 'smtp');
        $this->assertVariableIsHandled('MAILER_HOST', 'mailer.host@mailer.com');
        $this->assertVariableIsHandled('MAILER_PORT', 25);
        $this->assertVariableIsHandled('MAILER_USERNAME', 'username@test.com');
        $this->assertVariableIsHandled('MAILER_PASSWORD', 'testpassword');
        $this->assertVariableIsHandled('MAILER_FROM_EMAIL', 'username@test.com');
        $this->assertVariableIsHandled('MAILER_FROM_NAME', 'Mailer test');

        $this->assertVariableIsHandled('XDEBUG_REMOTE_HOST', '192.168.1.129');
        $this->assertVariableIsHandled('XDEBUG_REMOTE_PORT', 9000);
    }

    /**
     * Assert qu'une variable et sa valeur soit contenue dans $_ENV, $_SERVER et getenv()
     *
     * @param bool|string|int|float $expected
     */
    private function assertVariableIsHandled(string $name, $expected): void
    {
        $this->assertArrayHasKey($name, $_SERVER, "\$_SERVER n'a pas la clef $name");
        $this->assertSame($expected, $_SERVER[$name], "\$_SERVER[\$name] n'a pas retourné expected $expected");

        $this->assertArrayHasKey($name, $_ENV, "\$_ENV n'a pas la clef $name");
        $this->assertSame($expected, $_ENV[$name], "\$_ENV[\$name] n'a pas retourné expected $expected");

        // Casté en string
        $this->assertEquals($expected, getenv($name), "getenv($name) n'a pas retourné expected $expected");
    }

    protected function tearDown(): void
    {
        $_ENV = $_SERVER = [];

        $this->deleteFiles();
    }

    /**
     * Supprime les eventuels fichiers créés si l'on exit le script
     */
    public function __destruct()
    {
        $this->deleteFiles();
    }

    /**
     * Créé un fichier au chemin $path avec les données $data (par défaut fichier vide) et assert que le fichier existe
     *
     * @return string Le chemin
     */
    protected function createFile(string $path, string $data = ''): string
    {
        file_put_contents($path, $data);

        $this->assertFileExists($path);

        $this->filesToDelete[] = $path;

        return $path;
    }

    /**
     * On delete des fichiers si ils ont été créés pendant les tests unitaires
     */
    private function deleteFiles(): void
    {
        if (!$this->filesToDelete) {
            return;
        }

        foreach ($this->filesToDelete as $file) {
            if (!is_file($file) && !is_dir($file)) {
                continue;
            }

            exec('rm -rf ' . $file);
        }
    }
}

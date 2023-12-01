<?php

namespace RayanLevert\Dotenv\Tests;

use RayanLevert\Dotenv\Dotenv;
use RayanLevert\Dotenv\Exception;

class DotenvTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array<int, string> Fichiers à delete après chaque test unitaire
     */
    protected array $filesToDelete = [];

    protected function tearDown(): void
    {
        $_ENV = $_SERVER = [];

        foreach (getenv() as $variable => $value) {
            putenv($variable);
        }

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
     * @test Test le fichier d'un string empty
     */
    public function testConstructorEmptyString(): void
    {
        $this->expectExceptionObject(new Exception(' n\'existe pas'));

        new Dotenv('');
    }

    /**
     * @test Test le fichier d'un string qui n'est pas un fichier
     */
    public function testConstructorNotFile(): void
    {
        $this->expectExceptionObject(new Exception('test n\'existe pas'));

        new Dotenv('test');
    }

    /**
     * @test Test un fichier sans aucune donnée -> $_ENV empty
     */
    public function testConstructorEmptyFile(): void
    {
        (new Dotenv($this->createFile('/app/data/.env')))->load();

        $this->assertSame([], $_ENV);
    }

    /**
     * @test Test un fichier sans aucune donnée avec du required -> exception
     */
    public function testConstructorEmptyFileWithRequired(): void
    {
        $this->expectExceptionObject(new Exception('Missing env variables : APP_PATH'));

        (new Dotenv($this->createFile('/app/data/.env')))->load()->required(['APP_PATH']);
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
     * @test Test une variable avec un diez -> il faut qu'il y ait un espace pour considérer un commentaire
     */
    public function testValueWithDiez(): void
    {
        $this->createFile(
            '/app/data/.env',
            "TEST=valueWith#\nTEST2=valueWith#InBetween\nTEST3=#\nTEST4=v a l u e # ici est le commentaire\nTEST5=test"
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('TEST', 'valueWith#');
        $this->assertVariableIsHandled('TEST2', 'valueWith#InBetween');
        $this->assertVariableIsHandled('TEST3', '#');
        $this->assertVariableIsHandled('TEST4', 'v a l u e');
        $this->assertVariableIsHandled('TEST5', 'test');
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
     * @test Test une seule variable à multiline
     */
    public function testMultiLineOnlyVariable(): void
    {
        $this->createFile(
            '/app/data/.env',
            'TEST="First
Second
Third
Line"'
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled(
            'TEST',
            'First
Second
Third
Line'
        );
    }

    /**
     * @test Test plusieurs variables avec une à multiline
     */
    public function testMultiLineNotOnlyVariable(): void
    {
        $this->createFile(
            '/app/data/.env',
            "TEST2=test2\n" . 'TEST="First
Second
Third
Line"
ANOTHERTEST=testvalue
ANOTHERTEST2=testvalue3'
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('TEST2', 'test2');
        $this->assertVariableIsHandled(
            'TEST',
            'First
Second
Third
Line'
        );
        $this->assertVariableIsHandled('ANOTHERTEST', 'testvalue');
        $this->assertVariableIsHandled('ANOTHERTEST2', 'testvalue3');
    }

    /**
     * @test Test dans la variable multiline ayant des égales -> ne sont pas traitées car skip
     */
    public function testMultiLineWithEquals(): void
    {
        $_ENV = $_SERVER = [];

        $this->createFile(
            '/app/data/.env',
            "TEST2=test2\n" . 'TEST="Firs=t
Second
Th=ird
Lin=e"
ANOTHERTEST=testvalue
ANOTHERTEST2=testvalue3'
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertCount(4, $_ENV);
        $this->assertVariableIsHandled('TEST2', 'test2');
        $this->assertVariableIsHandled('ANOTHERTEST2', 'testvalue3');
        $this->assertVariableIsHandled('ANOTHERTEST', 'testvalue');
        $this->assertVariableIsHandled(
            'TEST',
            'Firs=t
Second
Th=ird
Lin=e'
        );
    }

    /**
     * @test Test une variable qui ne ferme pas la quote -> exception
     */
    public function testMultilineNotClosingDoubleQuoteOneLine(): void
    {
        $this->createFile('/app/data/.env', 'TEST="Je ne ferme pas la quote');

        $this->expectExceptionObject(
            new Exception("Une variable a une double quote (\") qui ne se ferme pas, variable: TEST")
        );

        (new Dotenv('/app/data/.env'))->load();
    }

    /**
     * @test Test une variable qui se présente sur plusieurs lignes sans fermer sa double quote -> exception
     */
    public function testMultilineNotClosingDoubleQuoteMultipleLines(): void
    {
        $this->createFile('/app/data/.env', "TEST=\"Je ne ferme pas la quote\nPas cette ligne\nNi la suivante");

        $this->expectExceptionObject(
            new Exception("Une variable a une double quote (\") qui ne se ferme pas, variable: TEST")
        );

        (new Dotenv('/app/data/.env'))->load();
    }

    /**
     * @test Test une variable nested en première déclaratation et deux autres qui l'utilise
     */
    public function testNestedVariableBeginningInFile(): void
    {
        $this->createFile('/app/data/.env', "NESTED=nestedValue\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('NESTED', 'nestedValue');
        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');
    }

    /**
     * @test Test une variable dans le getenv nested
     */
    public function testNestedVariableGetEnv(): void
    {
        putenv('NESTED=nestedValue');
        $this->assertSame('nestedValue', getenv('NESTED'));

        $this->createFile('/app/data/.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');

        // Remove la variable d'env dans le getenv
        putenv('NESTED');
    }

    /**
     * @test Test une variable déjà présente dans le $_SERVER
     */
    public function testNestedVariableServer(): void
    {
        $_SERVER['NESTED'] = 'nested';

        $this->createFile('/app/data/.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('TEST', 'nested');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    /**
     * @test Test une variable nested en integer
     */
    public function testNestedVariableInt(): void
    {
        $this->createFile('/app/data/.env', "NESTED=1\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('NESTED', 1);
        $this->assertVariableIsHandled('TEST', 1);
        $this->assertVariableIsHandled('TEST2', '1/test');
    }

    /**
     * @test Test une variable nested en float
     */
    public function testNestedVariableFloat(): void
    {
        $this->createFile('/app/data/.env', "NESTED=1.2\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('NESTED', 1.2);
        $this->assertVariableIsHandled('TEST', 1.2);
        $this->assertVariableIsHandled('TEST2', '1.2/test');
    }

    /**
     * @test Test une variable qui se déclare via deux nested variables
     */
    public function testTwoNestedVariablesInOneDeclaration(): void
    {
        $this->createFile(
            '/app/data/.env',
            "NESTED=nested\nNESTED2=nested2\nTEST=\${NESTED}/\${NESTED2}\nTEST2=\${NESTED}/test"
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('NESTED2', 'nested2');
        $this->assertVariableIsHandled('TEST', 'nested/nested2');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    /**
     * @test Test une variable nested non trouvée -> exception
     */
    public function testNestedVariableNotFound(): void
    {
        $this->createFile('/app/data/.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        $this->expectExceptionObject(new Exception('Variable d\'env nested NESTED non trouvée par PHP'));

        (new Dotenv('/app/data/.env'))->load();
    }

    /**
     * @test Test une variable nested qui ne finit pas sa bracket -> valeur brûte
     */
    public function testNestedVariableNotEndingBracket(): void
    {
        $this->createFile('/app/data/.env', "TEST=\${NESTED\nTEST2=ok");

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('TEST', '${NESTED');
        $this->assertVariableIsHandled('TEST2', 'ok');
    }

    /**
     * @test Test une variable qui est en multi line avec des nested variables
     */
    public function testNestedAndDoubleQuoteMultiLine(): void
    {
        $this->createFile(
            '/app/data/.env',
            "NESTED=nested
NESTED2=-test
TEST=\"\${NESTED}
deuxième-ligne
troisième\${NESTED2}-ligne\""
        );

        (new Dotenv('/app/data/.env'))->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('TEST', "nested\ndeuxième-ligne\ntroisième-test-ligne");
    }

    /**
     * @test Test le fichier test.env, sample d'un vrai fichier .env
     */
    public function testSampleTestEnvFile(): void
    {
        (new Dotenv('/app/tests/test.env'))->load();

        $this->assertVariableIsHandled('APP_ENV', 'development');
        $this->assertVariableIsHandled('APP_NAME', 'Nom de l\'application test');
        $this->assertVariableIsHandled('APP_URL', 'https://test.local.fr');
        $this->assertVariableIsHandled('APP_CACHE', '/app/cache');
        $this->assertVariableIsHandled('APP_LOGS', '/app/logs');

        $this->assertVariableIsHandled('WEB_DOCUMENT_ROOT', '/app/public');
        $this->assertVariableIsHandled('WEB_ALIAS_DOMAIN', 'test.local.fr');
        $this->assertVariableIsHandled('WEB_DOCUMENT_ASSETS', '/app/public/assets');

        $this->assertVariableIsHandled('MYSQL_ROOT_PASSWORD', 'root');
        $this->assertVariableIsHandled('MYSQL_DATABASE', 'test_base');
        $this->assertVariableIsHandled('MYSQL_USER', 'root');
        $this->assertVariableIsHandled('MYSQL_PASSWORD', 'root');
        $this->assertVariableIsHandled('MYSQL_PORT', 33061);
        $this->assertVariableIsHandled('MYSQL_HOST', 'localhost');
        $this->assertVariableIsHandled('MYSQL_DSN', 'mysql:host=localhost;dbname=test_base;port=33061');

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
        $this->assertVariableIsHandled(
            'SECURITY_RSA_PUBLIC_KEY',
            "-----BEGIN RSA PUBLIC KEY-----\n...\nKh9NV...\n...\n-----END RSA PUBLIC KEY-----"
        );

        $this->assertVariableIsHandled(
            'SECURITY_RSA_PUBLIC_KEY',
            '-----BEGIN RSA PUBLIC KEY-----
...
Kh9NV...
...
-----END RSA PUBLIC KEY-----'
        );

        $this->assertVariableIsHandled('MAILER_DRIVER', 'smtp');
        $this->assertVariableIsHandled('MAILER_HOST', 'mailer.host@mailer.com');
        $this->assertVariableIsHandled('MAILER_PORT', 25);
        $this->assertVariableIsHandled('MAILER_USERNAME', 'username@test.com');
        $this->assertVariableIsHandled('MAILER_PASSWORD', 'passwordAvecUn#DansLaValeur');
        $this->assertVariableIsHandled('MAILER_FROM_EMAIL', 'username@test.com');
        $this->assertVariableIsHandled('MAILER_FROM_NAME', 'Mailer test');

        $this->assertVariableIsHandled('XDEBUG_REMOTE_HOST', '192.168.1.129');
        $this->assertVariableIsHandled('XDEBUG_REMOTE_PORT', 9000);
    }

    /**
     * Assert qu'une variable et sa valeur soit contenue dans $_ENV, $_SERVER et getenv()
     */
    private function assertVariableIsHandled(string $name, bool|string|int|float $expected): void
    {
        $this->assertArrayHasKey($name, $_SERVER, "\$_SERVER n'a pas la clef $name");
        $this->assertSame($expected, $_SERVER[$name], "\$_SERVER[$name] n'a pas retourné expected $expected");

        $this->assertArrayHasKey($name, $_ENV, "\$_ENV n'a pas la clef $name");
        $this->assertSame($expected, $_ENV[$name], "\$_ENV[\$name] n'a pas retourné expected $expected");

        // Casté en string
        $this->assertSame(strval($expected), getenv($name), "getenv($name) n'a pas retourné expected $expected");
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

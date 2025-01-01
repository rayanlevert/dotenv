<?php

namespace RayanLevert\Dotenv\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RayanLevert\Dotenv\Dotenv;
use RayanLevert\Dotenv\Exception;

use function getenv;
use function putenv;
use function is_file;
use function is_dir;
use function exec;

#[CoversClass(Dotenv::class)]
class DotenvTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<int, string> Files to delete after each test */
    protected array $filesToDelete = [];

    /** Path of the tested environment file */
    protected string $envFile;

    protected function setUp(): void
    {
        $this->envFile = dirname(__DIR__) . '/data/.env';
    }

    protected function tearDown(): void
    {
        $_ENV = $_SERVER = [];

        // Resets all getenv() variables
        foreach (getenv() as $variable => $value) {
            putenv($variable);
        }

        $this->deleteFiles();
    }

    /**
     * Deletes files if we exit the script
     */
    public function __destruct()
    {
        $this->deleteFiles();
    }

    /**
     * empty string
     */
    #[Test]
    public function testConstructorEmptyString(): void
    {
        $this->expectExceptionObject(new Exception('Environment file  is not readable'));

        new Dotenv('');
    }

    /**
     * not a file
     */
    #[Test]
    public function testConstructorNotFile(): void
    {
        $this->expectExceptionObject(new Exception('Environment file test is not readable'));

        new Dotenv('test');
    }

    /**
     * empty file -> $_ENV empty
     */
    #[Test]
    public function testConstructorEmptyFile(): void
    {
        $filePath = $this->createFile('.env');
        $oEnv     = new Dotenv($filePath)->load();

        $this->assertSame($filePath, $oEnv->filePath);
        $this->assertSame([], $_ENV);
    }

    /**
     * required envs with an empty file -> exception
     */
    #[Test]
    public function testConstructorEmptyFileWithRequired(): void
    {
        $this->expectExceptionObject(new Exception('Missing env variables : APP_PATH'));

        new Dotenv($this->createFile('.env'))->load()->required(...['APP_PATH']);
    }

    /**
     * correct file
     */
    #[Test]
    public function testConstructorCorrectFile(): void
    {
        $this->createFile('.env', 'test=test');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile));
    }

    /**
     * with a # before the declaration
     */
    #[Test]
    public function testFileWithHashDebut(): void
    {
        $this->createFile('.env', "# TEST=test");

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertArrayNotHasKey('TEST', $_ENV);
    }

    /**
     * variables with a #
     */
    #[Test]
    public function testValueWithDiez(): void
    {
        $this->createFile(
            '.env',
            "TEST=valueWith#\nTEST2=valueWith#InBetween\nTEST3=#\nTEST4=v a l u e # here is the comment\nTEST5=test"
        );

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('TEST', 'valueWith#');
        $this->assertVariableIsHandled('TEST2', 'valueWith#InBetween');
        $this->assertVariableIsHandled('TEST3', '#');
        $this->assertVariableIsHandled('TEST4', 'v a l u e');
        $this->assertVariableIsHandled('TEST5', 'test');
    }

    /**
     * line without an = -> not handled
     */
    #[Test]
    public function testFileWithNoEqual(): void
    {
        $this->createFile('.env', "TEST\nTEST2=correct");

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());

        $this->assertArrayNotHasKey('TEST', $_ENV);
        $this->assertVariableIsHandled('TEST2', 'correct');
    }

    /**
     * variable with multiple =
     */
    #[Test]
    public function testFileWithMoreThan2Equals(): void
    {
        $this->createFile('.env', "TEST=test=te\nTE=t=e=s=t");

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());

        $this->assertVariableIsHandled('TEST', 'test=te');
        $this->assertVariableIsHandled('TE', 't=e=s=t');
    }

    /**
     * two same variables = first one is handled
     */
    #[Test]
    public function testFileWithSameVariable(): void
    {
        $this->createFile('.env', "TEST=test1\nTEST=test2");

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', 'test1');
    }

    /**
     * float variable (with a dot)
     */
    #[Test]
    public function testFileWithFloatValue(): void
    {
        $this->createFile('.env', 'TEST=34.3');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', 34.3);
    }

    /**
     * float variable with a comma -> string
     */
    #[Test]
    public function testFileWithFloatValueButComma(): void
    {
        $this->createFile('.env', 'TEST=34,3');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', '34,3');
    }

    /**
     * integer variable
     */
    #[Test]
    public function testFileWithIntValue(): void
    {
        $this->createFile('.env', 'TEST=34');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', 34);
    }

    /**
     * true variable
     */
    #[Test]
    public function testFileWithTrueValue(): void
    {
        $this->createFile('.env', 'TEST=true');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', true);
    }

    /**
     * false variable
     */
    #[Test]
    public function testFileWithFalseValue(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->assertInstanceOf(Dotenv::class, new Dotenv($this->envFile)->load());
        $this->assertVariableIsHandled('TEST', false);
    }

    /**
     * empty required array => no exception
     */
    #[Test]
    public function testRequiredEmptyEnvs(): void
    {
        $this->createFile('.env', 'TEST=false');

        $oDotenv = new Dotenv($this->envFile)->load();
        $oDotenv->required(...[]);

        $this->assertInstanceOf(Dotenv::class, $oDotenv);
    }

    /**
     * required a non existing variable
     */
    #[Test]
    public function testRequiredOneEnvMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        new Dotenv($this->envFile)->load()->required(...['TESTNOTIN']);
    }

    /**
     * two required non existing variables
     */
    #[Test]
    public function testRequiredTwoEnvMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN, TESTNOTIN2'));
        new Dotenv($this->envFile)->load()->required(...['TESTNOTIN', 'TESTNOTIN2']);
    }

    /**
     * one required existing and another one not
     */
    #[Test]
    public function testRequiredOneInOneNotMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        new Dotenv($this->envFile)->load()->required(...['TESTNOTIN', 'TEST']);
    }

    /**
     * required variable case sensitive => exception
     */
    #[Test]
    public function testRequiredOneCaseSensitive(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : test'));
        new Dotenv($this->envFile)->load()->required(...['test']);
    }

    /**
     * one multiline variable
     */
    #[Test]
    public function testMultiLineOnlyVariable(): void
    {
        $this->createFile(
            '.env',
            'TEST="First
Second
Third
Line"'
        );

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled(
            'TEST',
            'First
Second
Third
Line'
        );
    }

    /**
     * multiple multiline variables
     */
    #[Test]
    public function testMultiLineNotOnlyVariable(): void
    {
        $this->createFile(
            '.env',
            "TEST2=test2\n" . 'TEST="First
Second
Third
Line"
ANOTHERTEST=testvalue
ANOTHERTEST2=testvalue3'
        );

        new Dotenv($this->envFile)->load();

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
     * multiline variable with equals -> not handled because skipped
     */
    #[Test]
    public function testMultiLineWithEquals(): void
    {
        $_ENV = $_SERVER = [];

        $this->createFile(
            '.env',
            "TEST2=test2\n" . 'TEST="Firs=t
Second
Th=ird
Lin=e"
ANOTHERTEST=testvalue
ANOTHERTEST2=testvalue3'
        );

        new Dotenv($this->envFile)->load();

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
     * variable doesn't close its quote -> exception
     */
    #[Test]
    public function testMultilineNotClosingDoubleQuoteOneLine(): void
    {
        $this->createFile('.env', 'TEST="Je ne ferme pas la quote');

        $this->expectExceptionObject(
            new Exception("Environment variable has a double quote (\") not closing in, variable: TEST")
        );

        new Dotenv($this->envFile)->load();
    }

    /**
     * multiline variable not closing its quote -> exception
     */
    #[Test]
    public function testMultilineNotClosingDoubleQuoteMultipleLines(): void
    {
        $this->createFile('.env', "TEST=\"Je ne ferme pas la quote\nPas cette ligne\nNi la suivante");

        $this->expectExceptionObject(
            new Exception("Environment variable has a double quote (\") not closing in, variable: TEST")
        );

        new Dotenv($this->envFile)->load();
    }

    /**
     * nested variable in first declaration and others using it
     */
    #[Test]
    public function testNestedVariableBeginningInFile(): void
    {
        $this->createFile('.env', "NESTED=nestedValue\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('NESTED', 'nestedValue');
        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');
    }

    /**
     * nested variable in the getenv() (from OS for example)
     */
    #[Test]
    public function testNestedVariableGetEnv(): void
    {
        putenv('NESTED=nestedValue');
        $this->assertSame('nestedValue', getenv('NESTED'));

        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');

        // Remove la variable d'env dans le getenv
        putenv('NESTED');
    }

    /**
     * nested variable in the $_SERVER
     */
    #[Test]
    public function testNestedVariableServer(): void
    {
        $_SERVER['NESTED'] = 'nested';

        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('TEST', 'nested');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    /**
     * nested integer variable
     */
    #[Test]
    public function testNestedVariableInt(): void
    {
        $this->createFile('.env', "NESTED=1\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('NESTED', 1);
        $this->assertVariableIsHandled('TEST', 1);
        $this->assertVariableIsHandled('TEST2', '1/test');
    }

    /**
     * nested float variable
     */
    #[Test]
    public function testNestedVariableFloat(): void
    {
        $this->createFile('.env', "NESTED=1.2\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('NESTED', 1.2);
        $this->assertVariableIsHandled('TEST', 1.2);
        $this->assertVariableIsHandled('TEST2', '1.2/test');
    }

    /**
     * variable uses two nested variables
     */
    #[Test]
    public function testTwoNestedVariablesInOneDeclaration(): void
    {
        $this->createFile(
            '.env',
            "NESTED=nested\nNESTED2=nested2\nTEST=\${NESTED}/\${NESTED2}\nTEST2=\${NESTED}/test"
        );

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('NESTED2', 'nested2');
        $this->assertVariableIsHandled('TEST', 'nested/nested2');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    /**
     * nested variable not found -> exception
     */
    #[Test]
    public function testNestedVariableNotFound(): void
    {
        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        $this->expectExceptionObject(new Exception('Nested environment variable NESTED not found'));

        new Dotenv($this->envFile)->load();
    }

    /**
     * nested variable not ending with a bracket -> raw value
     */
    #[Test]
    public function testNestedVariableNotEndingBracket(): void
    {
        $this->createFile('.env', "TEST=\${NESTED\nTEST2=ok");

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('TEST', '${NESTED');
        $this->assertVariableIsHandled('TEST2', 'ok');
    }

    /**
     * multiline variable with nested variables
     */
    #[Test]
    public function testNestedAndDoubleQuoteMultiLine(): void
    {
        $this->createFile(
            '.env',
            "NESTED=nested
NESTED2=-test
TEST=\"\${NESTED}
deuxième-ligne
troisième\${NESTED2}-ligne\""
        );

        new Dotenv($this->envFile)->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('TEST', "nested\ndeuxième-ligne\ntroisième-test-ligne");
    }

    /**
     * testing a potential 'production' .env
     */
    #[Test]
    public function testSampleTestEnvFile(): void
    {
        new Dotenv(__DIR__ . '/test.env')->load();

        $this->assertVariableIsHandled('APP_ENV', 'development');
        $this->assertVariableIsHandled('APP_NAME', 'Name of the application');
        $this->assertVariableIsHandled('APP_URL', 'https://test.local.com');
        $this->assertVariableIsHandled('APP_CACHE', '/app/cache');
        $this->assertVariableIsHandled('APP_LOGS', '/app/logs');

        $this->assertVariableIsHandled('WEB_DOCUMENT_ROOT', '/app/public');
        $this->assertVariableIsHandled('WEB_ALIAS_DOMAIN', 'test.local.com');
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
        $this->assertVariableIsHandled('MAILER_PASSWORD', 'passwordWithAn#InTheValue');
        $this->assertVariableIsHandled('MAILER_FROM_EMAIL', 'username@test.com');
        $this->assertVariableIsHandled('MAILER_FROM_NAME', 'Mailer test');

        $this->assertVariableIsHandled('XDEBUG_REMOTE_HOST', '192.168.1.129');
        $this->assertVariableIsHandled('XDEBUG_REMOTE_PORT', 9000);
    }

    /**
     * Asserts a variable and its value is in $_ENV, $_SERVER and getenv()
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
     * Creates a file at path `../data/$file` with data `$data`
     *
     * @throws \Exception If the file has not been created
     *
     * @return string The path
     */
    protected function createFile(string $file, string $data = ''): string
    {
        $file = dirname(__DIR__) . '/data/' . $file;

        if (file_put_contents($file, $data) === false) {
            throw new \Exception("file_put_contents($file) returned false");
        }

        $this->filesToDelete[] = $file;

        return $file;
    }

    /**
     * Deletes files created in the tests
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

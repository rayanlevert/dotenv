<?php

namespace RayanLevert\Dotenv\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RayanLevert\Dotenv\Dotenv;
use RayanLevert\Dotenv\Exception;

use function exec;
use function getenv;
use function is_dir;
use function is_file;
use function putenv;

#[CoversClass(Dotenv::class)]
class DotenvTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array<int, string> Files to delete after each test
     */
    protected array $filesToDelete = [];

    /**
     * Path of the tested environment file
     */
    protected string $envFile;

    /**
     * Deletes files if we exit the script
     */
    public function __destruct()
    {
        $this->deleteFiles();
    }

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

    #[Test]
    #[TestDox('empty string')]
    public function constructorEmptyString(): void
    {
        $this->expectExceptionObject(new Exception('Environment file  is not readable'));

        new Dotenv('');
    }

    #[Test]
    #[TestDox('not a file')]
    public function constructorNotFile(): void
    {
        $this->expectExceptionObject(new Exception('Environment file test is not readable'));

        new Dotenv('test');
    }

    #[Test]
    #[TestDox('empty file -> $_ENV empty')]
    public function constructorEmptyFile(): void
    {
        (new Dotenv($this->createFile('.env')))->load();

        $this->assertSame([], $_ENV);
    }

    #[Test]
    #[TestDox('required envs with an empty file -> exception')]
    public function constructorEmptyFileWithRequired(): void
    {
        $this->expectExceptionObject(new Exception('Missing env variables : APP_PATH'));

        (new Dotenv($this->createFile('.env')))->load()->required(...['APP_PATH']);
    }

    #[Test]
    #[TestDox('correct file')]
    public function constructorCorrectFile(): void
    {
        $this->createFile('.env', 'test=test');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile)));
    }

    #[Test]
    #[TestDox('with a # before the declaration')]
    public function fileWithHashDebut(): void
    {
        $this->createFile('.env', '# TEST=test');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertArrayNotHasKey('TEST', $_ENV);
    }

    #[Test]
    #[TestDox('variables with a #')]
    public function valueWithDiez(): void
    {
        $this->createFile(
            '.env',
            "TEST=valueWith#\nTEST2=valueWith#InBetween\nTEST3=#\nTEST4=v a l u e # here is the comment\nTEST5=test"
        );

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('TEST', 'valueWith#');
        $this->assertVariableIsHandled('TEST2', 'valueWith#InBetween');
        $this->assertVariableIsHandled('TEST3', '#');
        $this->assertVariableIsHandled('TEST4', 'v a l u e');
        $this->assertVariableIsHandled('TEST5', 'test');
    }

    #[Test]
    #[TestDox('line without an = -> not handled')]
    public function fileWithNoEqual(): void
    {
        $this->createFile('.env', "TEST\nTEST2=correct");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());

        $this->assertArrayNotHasKey('TEST', $_ENV);
        $this->assertVariableIsHandled('TEST2', 'correct');
    }

    #[Test]
    #[TestDox('variable with multiple =')]
    public function fileWithMoreThan2Equals(): void
    {
        $this->createFile('.env', "TEST=test=te\nTE=t=e=s=t");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());

        $this->assertVariableIsHandled('TEST', 'test=te');
        $this->assertVariableIsHandled('TE', 't=e=s=t');
    }

    #[Test]
    #[TestDox('two same variables = first one is handled')]
    public function fileWithSameVariable(): void
    {
        $this->createFile('.env', "TEST=test1\nTEST=test2");

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', 'test1');
    }

    #[Test]
    #[TestDox('float variable (with a dot)')]
    public function fileWithFloatValue(): void
    {
        $this->createFile('.env', 'TEST=34.3');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', 34.3);
    }

    #[Test]
    #[TestDox('float variable with a comma -> string')]
    public function fileWithFloatValueButComma(): void
    {
        $this->createFile('.env', 'TEST=34,3');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', '34,3');
    }

    #[Test]
    #[TestDox('integer variable')]
    public function fileWithIntValue(): void
    {
        $this->createFile('.env', 'TEST=34');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', 34);
    }

    #[Test]
    #[TestDox('true variable')]
    public function fileWithTrueValue(): void
    {
        $this->createFile('.env', 'TEST=true');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', true);
    }

    #[Test]
    #[TestDox('false variable')]
    public function fileWithFalseValue(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->assertInstanceOf(Dotenv::class, (new Dotenv($this->envFile))->load());
        $this->assertVariableIsHandled('TEST', false);
    }

    #[Test]
    #[TestDox('empty required array => no exception')]
    public function requiredEmptyEnvs(): void
    {
        $this->createFile('.env', 'TEST=false');

        $oDotenv = (new Dotenv($this->envFile))->load();
        $oDotenv->required(...[]);

        $this->assertInstanceOf(Dotenv::class, $oDotenv);
    }

    #[Test]
    #[TestDox('required a non existing variable')]
    public function requiredOneEnvMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        (new Dotenv($this->envFile))->load()->required(...['TESTNOTIN']);
    }

    #[Test]
    #[TestDox('two required non existing variables')]
    public function requiredTwoEnvMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN, TESTNOTIN2'));
        (new Dotenv($this->envFile))->load()->required(...['TESTNOTIN', 'TESTNOTIN2']);
    }

    #[Test]
    #[TestDox('one required existing and another one not')]
    public function requiredOneInOneNotMissing(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : TESTNOTIN'));
        (new Dotenv($this->envFile))->load()->required(...['TESTNOTIN', 'TEST']);
    }

    #[Test]
    #[TestDox('required variable case sensitive => exception')]
    public function requiredOneCaseSensitive(): void
    {
        $this->createFile('.env', 'TEST=false');

        $this->expectExceptionObject(new Exception('Missing env variables : test'));
        (new Dotenv($this->envFile))->load()->required(...['test']);
    }

    #[Test]
    #[TestDox('one multiline variable')]
    public function multiLineOnlyVariable(): void
    {
        $this->createFile(
            '.env',
            'TEST="First
Second
Third
Line"'
        );

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled(
            'TEST',
            'First
Second
Third
Line'
        );
    }

    #[Test]
    #[TestDox('multiple multiline variables')]
    public function multiLineNotOnlyVariable(): void
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

        (new Dotenv($this->envFile))->load();

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

    #[Test]
    #[TestDox('multiline variable with equals -> not handled because skipped')]
    public function multiLineWithEquals(): void
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

        (new Dotenv($this->envFile))->load();

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

    #[Test]
    #[TestDox('variable doesn\'t close its quote -> exception')]
    public function multilineNotClosingDoubleQuoteOneLine(): void
    {
        $this->createFile('.env', 'TEST="Je ne ferme pas la quote');

        $this->expectExceptionObject(
            new Exception('Environment variable has a double quote (") not closing in, variable: TEST')
        );

        (new Dotenv($this->envFile))->load();
    }

    #[Test]
    #[TestDox('multiline variable not closing its quote -> exception')]
    public function multilineNotClosingDoubleQuoteMultipleLines(): void
    {
        $this->createFile('.env', "TEST=\"Je ne ferme pas la quote\nPas cette ligne\nNi la suivante");

        $this->expectExceptionObject(
            new Exception('Environment variable has a double quote (") not closing in, variable: TEST')
        );

        (new Dotenv($this->envFile))->load();
    }

    #[Test]
    #[TestDox('nested variable in first declaration and others using it')]
    public function nestedVariableBeginningInFile(): void
    {
        $this->createFile('.env', "NESTED=nestedValue\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('NESTED', 'nestedValue');
        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');
    }

    #[Test]
    #[TestDox('nested variable in the getenv() (from OS for example)')]
    public function nestedVariableGetEnv(): void
    {
        putenv('NESTED=nestedValue');
        $this->assertSame('nestedValue', getenv('NESTED'));

        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('TEST', 'nestedValue');
        $this->assertVariableIsHandled('TEST2', 'nestedValue/test');

        // Remove la variable d'env dans le getenv
        putenv('NESTED');
    }

    #[Test]
    #[TestDox('nested variable in the $_SERVER')]
    public function nestedVariableServer(): void
    {
        $_SERVER['NESTED'] = 'nested';

        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('TEST', 'nested');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    #[Test]
    #[TestDox('nested integer variable')]
    public function nestedVariableInt(): void
    {
        $this->createFile('.env', "NESTED=1\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('NESTED', 1);
        $this->assertVariableIsHandled('TEST', 1);
        $this->assertVariableIsHandled('TEST2', '1/test');
    }

    #[Test]
    #[TestDox('nested float variable')]
    public function nestedVariableFloat(): void
    {
        $this->createFile('.env', "NESTED=1.2\nTEST=\${NESTED}\nTEST2=\${NESTED}/test");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('NESTED', 1.2);
        $this->assertVariableIsHandled('TEST', 1.2);
        $this->assertVariableIsHandled('TEST2', '1.2/test');
    }

    #[Test]
    #[TestDox('variable uses two nested variables')]
    public function twoNestedVariablesInOneDeclaration(): void
    {
        $this->createFile(
            '.env',
            "NESTED=nested\nNESTED2=nested2\nTEST=\${NESTED}/\${NESTED2}\nTEST2=\${NESTED}/test"
        );

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('NESTED2', 'nested2');
        $this->assertVariableIsHandled('TEST', 'nested/nested2');
        $this->assertVariableIsHandled('TEST2', 'nested/test');
    }

    #[Test]
    #[TestDox('nested variable not found -> exception')]
    public function nestedVariableNotFound(): void
    {
        $this->createFile('.env', "TEST=\${NESTED}\nTEST2=\${NESTED}/test");

        $this->expectExceptionObject(new Exception('Nested environment variable NESTED not found'));

        (new Dotenv($this->envFile))->load();
    }

    #[Test]
    #[TestDox('nested variable not ending with a bracket -> raw value')]
    public function nestedVariableNotEndingBracket(): void
    {
        $this->createFile('.env', "TEST=\${NESTED\nTEST2=ok");

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('TEST', '${NESTED');
        $this->assertVariableIsHandled('TEST2', 'ok');
    }

    #[Test]
    #[TestDox('multiline variable with nested variables')]
    public function nestedAndDoubleQuoteMultiLine(): void
    {
        $this->createFile(
            '.env',
            'NESTED=nested
NESTED2=-test
TEST="${NESTED}
deuxième-ligne
troisième${NESTED2}-ligne"'
        );

        (new Dotenv($this->envFile))->load();

        $this->assertVariableIsHandled('NESTED', 'nested');
        $this->assertVariableIsHandled('TEST', "nested\ndeuxième-ligne\ntroisième-test-ligne");
    }

    #[Test]
    #[TestDox('testing a potential \'production\' .env')]
    public function sampleTestEnvFile(): void
    {
        (new Dotenv(__DIR__ . '/test.env'))->load();

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
     * Asserts a variable and its value is in $_ENV, $_SERVER and getenv()
     */
    private function assertVariableIsHandled(string $name, bool|float|int|string $expected): void
    {
        $this->assertArrayHasKey($name, $_SERVER, "\$_SERVER n'a pas la clef $name");
        $this->assertSame($expected, $_SERVER[$name], "\$_SERVER[$name] n'a pas retourné expected $expected");

        $this->assertArrayHasKey($name, $_ENV, "\$_ENV n'a pas la clef $name");
        $this->assertSame($expected, $_ENV[$name], "\$_ENV[\$name] n'a pas retourné expected $expected");

        // Casté en string
        $this->assertSame((string) $expected, getenv($name), "getenv($name) n'a pas retourné expected $expected");
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

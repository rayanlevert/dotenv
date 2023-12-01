## Simple and fast class handling an environment file to `$_ENV`, `$_SERVER` and `getenv()`

### Initializes the instance setting the file path

```php
$oDotenv = new \RayanLevert\Dotenv\Dotenv('file/to/.dotenv');
```
An exception `RayanLevert\Dotenv\Exception` will be thrown if the file is not readable

### Reads the file content and loads in `$_ENV`, `$_SERVER` et `getenv()`, values of each variable

```php
$oDotenv->load();
```

#### For each new line found, tries to set to the name of the variable its value after the `=` sign

```
TEST_VALUE1=value1 => $_ENV['TEST_VALUE1'] = value1
```

## If the value is a primitive value, the value will be casted to the `PHP` userland

```php
NAME=1 => $_ENV['NAME'] = 1
NAME=23.34 => $_ENV['NAME'] = 23.34 (float values will be casted only with a dot .)
NAME=true => $_ENV['NAME'] = true
NAME=false => $_ENV['NAME'] = false
NAME=string value => $_ENV['NAME'] = 'string value'
```

###  Multiline variables are also available ! (separated by `\n`), double quotes (`"`) will be used

    ```php
    NAME="This is a variable
    with
    multiple
    lines"
    ```

### Nested variables, declared beforehand via the same file or `getenv()` (set via the OS or docker for example)

```php
    NESTED=VALUE
    NAME=${NESTED}
    NAME2=${NESTED}/path

    $_ENV['NESTED'] = 'VALUE'
    $_ENV['NAME'] = 'VALUE'
    $_ENV['NAME2'] = 'VALUE/path'
```

Throw an `RayanLevert\Dotenv\Exception` if at least one variable is not present in the `$_ENV` superglobal

```php
$oDotenv->required(['FIRST_REQUIRED', 'SECOND_REQUIRED']);
```

Worth if we want required variables for application purposes, an exception will be throw to prevent some logic error

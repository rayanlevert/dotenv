## Gère les variables d'environnement d'une application PHP depuis un fichier .env

Initialise l'instance en passant le chemin du fichier

```php
$oDotenv = new \DisDev\Dotenv\Dotenv('file/to/.dotenv');
```

Une exception `DisDev\Dotenv\Exception` sera lancée si
- le fichier n'existe pas
- le fichier n'est pas readable par le script
- le fichier est vide

Analyse le fichier et ajoute chaque variable et sa valeur dans `$_ENV`, `$_SERVER` et `getenv()`

```php
$oDotenv->load();
```

Pour chaque nouvelle ligne trouvée, essaie de set au nom de la variable la valeur après le signe =

```
TEST_VALUE1=value1 => $_ENV['TEST_VALUE1'] = value1
```

Si la valeur assignée est un type primitif autre que string, la valeur sera castée

```php
NAME=1 => $_ENV['NAME'] = 1
NAME=23.34 => $_ENV['NAME'] = 23.34 (les valeurs float seront castées uniquement si un point . est trouvé)
NAME=true => $_ENV['NAME'] = true
NAME=false => $_ENV['NAME'] = false
NAME=value string => $_ENV['NAME'] = 'value string'
```

- Avoir des valeurs à plusieurs lignes (séparés par des `\n`), les doubles quotes (`"`) seront à utiliser

    ```php
    NAME="CECI EST UNE VARIABLE
    A
    PLUSIEURS
    LIGNES"
    ```

- Utilisation de 'nested' variables déclarées au préalable via le même fichier ou `getenv()` de PHP (set via l'OS ou docker par ex.)

```php
    NESTED=VALUE
    NAME=${NESTED}
    NAME2=${NESTED}/path

    $_ENV['NESTED'] = 'VALUE'
    $_ENV['NAME'] = 'VALUE'
    $_ENV['NAME2'] = 'VALUE/path'
```

Lève une `DisDev\Dotenv\Exception` si la ou les variables d'environnement passées en argument ne sont pas dans `$_ENV`

```php
$oDotenv->required(['FIRST_REQUIRED', 'SECOND_REQUIRED']);
```

Idéal si l'on veut certaines variables obligatoires pour une application, une exception sera lancée si l'on oublie une variable dans le fichier
OSReadLine
----------

OSReadLine (OS for Onesimus Systems) is a readline alternative for PHP. It fully supports unicode characters and implements the basic readline functionality such as history, line editing, and key mapping.

Example:

```php
include 'readline.php';

$readline = new onesimus\readline\Readline();
$line = $readline->readLine('prompt> ');
echo $line;
```

Installation
------------

You can install OSReadLine by cloning this git repo, or with composer.

Composer:

```json
{
    "require": {
        "onesimus-systems/osreadline": "dev-master"
    }
}
```

Credits
-------

Inspiration and help was taken from the readline implementation in the [Hoa\Console library](https://github.com/hoaproject/Console).

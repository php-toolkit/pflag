# PHP Flag

[![License](https://img.shields.io/packagist/l/toolkit/pflag.svg?style=flat-square)](LICENSE)
[![GitHub tag (latest SemVer)](https://img.shields.io/github/tag/php-toolkit/pflag)](https://github.com/php-toolkit/pflag)
[![Actions Status](https://github.com/php-toolkit/pflag/workflows/Unit-Tests/badge.svg)](https://github.com/php-toolkit/pflag/actions)
[![Php Version Support](https://img.shields.io/packagist/php-v/toolkit/pflag)](https://packagist.org/packages/toolkit/pflag)
[![Latest Stable Version](http://img.shields.io/packagist/v/toolkit/pflag.svg)](https://packagist.org/packages/toolkit/pflag)

Command line flag parse library

## Install

**composer**

```bash
composer require toolkit/pflag
```

## Use Flags

> TODO

## Use SFlags

SFlags - is an simple flags(options&argument) parser

### Examples

```php
use Toolkit\PFlag\SFlags;

$flags = ['--name', 'inhere', '--age', '99', '--tag', 'php', '-t', 'go', '--tag', 'java', '-f', 'arg0'];

$optRules = [
    'name', // string
    'age'   => 'int;required', // set required
    'tag,t' => FlagType::ARRAY,
    'f'     => FlagType::BOOL,
];
$argRules = [
    // some argument rules
];

$fs->setOptRules($optRules);
$fs->setArgRules($argRules);
$fs->parse($rawFlags);
// or use
// $fs->parseDefined($flags, $optRules, $argRules);

vdump($fs->getOpts(), $fs->getRawArgs());
```

Output:

```text
array(3) {
  ["name"]=> string(6) "inhere"
  ["tag"]=> array(3) {
    [0]=> string(3) "php"
    [1]=> string(2) "go"
    [2]=> string(4) "java"
  }
  ["f"]=> bool(true)
}
array(1) {
  [0]=> string(4) "arg0"
}
```

### Parse CLI Input

write the codes to an php file(see [example/sflags-demo.php](example/sflags-demo.php))

```php
use Toolkit\PFlag\SFlags;

$rawFlags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($rawFlags);

$optRules = [
    // some option rules
    'name', // string
    'age'   => 'int;required', // set required
    'tag,t' => FlagType::ARRAY,
    'f'     => FlagType::BOOL,
];
$argRules = [
    // some argument rules
    'string',
    // set name
    'arrArg' => 'array',
];

$fs = SFlags::new();
$fs->parseDefined($rawFlags, $optRules, $argRules);
```

Run demo:

```bash
php example/sflags-demo.php --name inhere --age 99 --tag go -t php -t java -f arg0 arr0 arr1
```

Output:

```text
array(4) {
  ["name"]=> string(6) "inhere"
  ["age"]=> int(99)
  ["tag"]=> array(3) {
    [0]=> string(2) "go"
    [1]=> string(3) "php"
    [2]=> string(4) "java"
  }
  ["f"]=> bool(true)
}
array(2) {
  [0]=> string(4) "arg0"
  [1]=> array(2) {
    [0]=> string(4) "arr0"
    [1]=> string(4) "arr1"
  }
}
```

### Get Value

**Options**

```php
$force = $fs->getOption('f'); // bool(true)
$age  = $fs->getOption('age'); // int(99)
$name = $fs->getOption('name'); // string(inhere)
$tags = $fs->getOption('tags'); // array{"php", "go", "java"}
```

**Arguments**

```php
$arg0 = $fs->getArg(0); // string(arg0)
// get an array arg
$arrArg = $fs->getArg(1); // array{"arr0", "arr1"}
// get value by name
$arrArg = $fs->getArg('arrArg'); // array{"arr0", "arr1"}
```

### Flag Rule

The options/arguments rules

- string value is rule(`type;required;default;desc`).
- array is define item `SFlags::DEFINE_ITEM`
- supportted type see `FlagType::*`

```php
$rules = [
     // v: only value, as name and use default type FlagType::STRING
     // k-v: key is name, value can be string|array
     'long,s',
     // name => rule
     'long,s' => 'int',
     'f'      => 'bool',
     'long'   => FlagType::STRING,
     'tags'   => 'array', // can also: ints, strings
     'name'   => 'type;required;default;the description message', // with default, desc, required
]
```

**For options**

- option allow set alias/shorts

> TIP: name `long,s` - first is the option name. remaining is short names.

**For arguments**

- arguemnt no alias/shorts
- array value only allow defined last

**Definition item**

The const `SFlags::DEFINE_ITEM`:

```php
    public const DEFINE_ITEM = [
        'name'      => '',
        'desc'      => '',
        'type'      => FlagType::STRING,
        // 'index'    => 0, // only for argument
        'required'  => false,
        'default'   => null,
        'shorts'    => [], // only for option
        // value validator
        'validator' => null,
        // 'category' => null
    ];
```

## Unit tests

```bash
phpunit
```

## License

[MIT](LICENSE)

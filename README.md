# PHP Flag

[![License](https://img.shields.io/packagist/l/toolkit/pflag.svg?style=flat-square)](LICENSE)
[![GitHub tag (latest SemVer)](https://img.shields.io/github/tag/php-toolkit/pflag)](https://github.com/php-toolkit/pflag)
[![Actions Status](https://github.com/php-toolkit/pflag/workflows/Unit-Tests/badge.svg)](https://github.com/php-toolkit/pflag/actions)
[![Php Version Support](https://img.shields.io/packagist/php-v/toolkit/pflag)](https://packagist.org/packages/toolkit/pflag)
[![Latest Stable Version](http://img.shields.io/packagist/v/toolkit/pflag.svg)](https://packagist.org/packages/toolkit/pflag)

Generic PHP command line flags parse library

## Install

**composer**

```bash
composer require toolkit/pflag
```

## Flags Usage

Flags - is an cli flags(options&argument) parser and manager.

> example codes please see [example/flags-demo.php](example/flags-demo.php)

### Create Flags

```php
use Toolkit\PFlag\Flags;

require dirname(__DIR__) . '/test/bootstrap.php';

$fs = Flags::new();
// with some config
$fs->setScriptFile($scriptFile);
/** @see Flags::$settings */
$fs->setSettings([
    'descNlOnOptLen' => 26
]);
```

### Define options

```php
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\Validator\EnumValidator;

// add options
// - quick add
$fs->addOpt('age', 'a', 'this is a int option', FlagType::INT);

// - use string rule
$fs->addOptByRule('name,n', 'string;true;;this is a string option');
// -- add multi option at once.
$fs->addOptsByRules([
    'tag,t' => 'strings;no;;array option, allow set multi times',
    'f'     => 'bool;no;;this is an bool option',
]);
// - use array rule
/** @see Flags::DEFINE_ITEM for array rule */
$fs->addOptByRule('name-is-very-lang', [
    'type'   => FlagType::STRING,
    'desc'   => 'option name is to lang, desc will print on newline',
    'shorts' => ['d','e','f'],
    // TIP: add validator limit input value.
    'validator' => EnumValidator::new(['one', 'two', 'three']),
]);

// - use Option
$opt = Option::new('str1', "this is  string option, \ndesc has multi line, \nhaha...");
$opt->setDefault('defVal');
$fs->addOption($opt);
```

### Define Arguments

```php
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\FlagType;

// add arguments
// - quick add
$fs->addArg('strArg1', 'the is string arg and is required', 'string', true);
// - use string rule
$fs->addArgByRule('intArg2', 'int;no;89;this is a int arg and with default value');
// - use Argument object
$arg = Argument::new('arrArg');
// OR $arg->setType(FlagType::ARRAY);
$arg->setType(FlagType::STRINGS);
$arg->setDesc("this is an array arg,\n allow multi value,\n must define at last");
$fs->addArgument($arg);
```

### Parse Input

```php
use Toolkit\PFlag\Flags;
use Toolkit\PFlag\FlagType;

if (!$fs->parse($flags)) {
    // on render help
    return;
}

vdump($fs->getOpts(), $fs->getArgs());
```

**Show help**

```bash
$ php example/flags-demo.php --help
```

Output:

![flags-demo](example/images/flags-demo.png)

**Run demo:**

```bash
$ php example/flags-demo.php --name inhere --age 99 --tag go -t php -t java -d one -f arg0 80 arr0 arr1
```

Output:

```text
array(6) {
  ["str1"]=> string(6) "defVal"
  ["name"]=> string(6) "inhere"
  ["age"]=> int(99)
  ["tag"]=> array(3) {
    [0]=> string(2) "go"
    [1]=> string(3) "php"
    [2]=> string(4) "java"
  }
  ["name-is-very-lang"]=> string(3) "one"
  ["f"]=> bool(true)
}
array(3) {
  [0]=> string(4) "arg0"
  [1]=> int(80)
  [2]=> array(2) {
    [0]=> string(4) "arr0"
    [1]=> string(4) "arr1"
  }
}
```

## SFlags

SFlags - is an simple flags(options&argument) parser and manager.

> `SFlags` only support add option/argument by rule string or define array.

### Methods

Options:

- `setOptRules(array $rules)`
- `addOptRule(string $name, string|array $rule)`

Arguments:

- `setArgRules(array $rules)`
- `addArgRule(string $name, string|array $rule)`

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

**Run demo:**

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

**Show help**

```bash
$ php example/sflags-demo.php --help
```

## Get Value

**Options**

```php
$force = $fs->getOpt('f'); // bool(true)
$age  = $fs->getOpt('age'); // int(99)
$name = $fs->getOpt('name'); // string(inhere)
$tags = $fs->getOpt('tags'); // array{"php", "go", "java"}
```

**Arguments**

```php
$arg0 = $fs->getArg(0); // string(arg0)
// get an array arg
$arrArg = $fs->getArg(1); // array{"arr0", "arr1"}
// get value by name
$arrArg = $fs->getArg('arrArg'); // array{"arr0", "arr1"}
```

## Flag Rule

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

- option allow set shorts

> TIP: name `long,s` - first is the option name. remaining is short names.

**For arguments**

- arguemnt no alias/shorts
- array value only allow defined last

**Definition item**

The const `Flags::DEFINE_ITEM`:

```php
public const DEFINE_ITEM = [
    'name'      => '',
    'desc'      => '',
    'type'      => FlagType::STRING,
    'showType'  => '', // use for show help
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

# PHP Flag

[![License](https://img.shields.io/packagist/l/toolkit/pflag.svg?style=flat-square)](LICENSE)
[![GitHub tag (latest SemVer)](https://img.shields.io/github/tag/php-toolkit/pflag)](https://github.com/php-toolkit/pflag)
[![Actions Status](https://github.com/php-toolkit/pflag/workflows/Unit-Tests/badge.svg)](https://github.com/php-toolkit/pflag/actions)
[![Php Version Support](https://img.shields.io/packagist/php-v/toolkit/pflag)](https://packagist.org/packages/toolkit/pflag)
[![Latest Stable Version](http://img.shields.io/packagist/v/toolkit/pflag.svg)](https://packagist.org/packages/toolkit/pflag)
[![Coverage Status](https://coveralls.io/repos/github/php-toolkit/pflag/badge.svg?branch=main)](https://coveralls.io/github/php-toolkit/pflag?branch=main)

Generic PHP command line flags parse library

> Github: [php-toolkit/pflag](https://github.com/php-toolkit/pflag)

## [中文说明](README.zh-CN.md)

## Features

- Generic command line options and arguments parser.
- Support set value data type(`int,string,bool,array`), will auto format input value.
- Support set multi short names for an option.
- Support set default value for option/argument.
- Support read flag value from ENV var.
- Support set option/argument is required.
- Support set validator for check input value.
- Support auto render beautiful help message.

**Flag Options**:

- Options start with `-` or `--`, and the first character must be a letter
- Support long option. eg: `--long` `--long value`
- Support short option. eg: `-s -a value`
- Support define array option
  - eg: `--tag php --tag go` will get `tag: [php, go]`

**Flag Arguments**:

- Support binding named arguemnt
- Support define array argument

## Install

**composer**

```bash
composer require toolkit/pflag
```

-----------

## Flags Usage

Flags - is an cli flags(options&argument) parser and manager.

> example codes please see [example/flags-demo.php](example/flags-demo.php)

### Create Flags

```php
use Toolkit\PFlag\Flags;

require dirname(__DIR__) . '/test/bootstrap.php';

$flags = $_SERVER['argv'];
// NOTICE: must shift first element.
$scriptFile = array_shift($flags);

$fs = Flags::new();
// can with some config
$fs->setScriptFile($scriptFile);
/** @see Flags::$settings */
$fs->setSettings([
    'descNlOnOptLen' => 26
]);

// ...
```

### Define options

Examples for add flag option define:

```php
use Toolkit\PFlag\Flag\Option;
use Toolkit\PFlag\FlagType;
use Toolkit\PFlag\Validator\EnumValidator;

// add options
// - quick add
$fs->addOpt('age', 'a', 'this is a int option', FlagType::INT);

// - use string rule
$fs->addOptByRule('name,n', 'string;this is a string option;true');

// -- add multi option at once.
$fs->addOptsByRules([
    'tag,t' => 'strings;array option, allow set multi times',
    'f'     => 'bool;this is an bool option',
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

Examples for add flag argument define:

```php
use Toolkit\PFlag\Flag\Argument;
use Toolkit\PFlag\FlagType;

// add arguments
// - quick add
$fs->addArg('strArg1', 'the is string arg and is required', 'string', true);

// - use string rule
$fs->addArgByRule('intArg2', 'int;this is a int arg and with default value;no;89');

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

// ...

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
# options
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

# arguments
array(3) {
  [0]=> string(4) "arg0"
  [1]=> int(80)
  [2]=> array(2) {
    [0]=> string(4) "arr0"
    [1]=> string(4) "arr1"
  }
}
```

-----------

## SFlags Usage

SFlags - is an simple flags(options&argument) parser and manager.

### Examples

```php
use Toolkit\PFlag\SFlags;

$flags = ['--name', 'inhere', '--age', '99', '--tag', 'php', '-t', 'go', '--tag', 'java', '-f', 'arg0'];

$optRules = [
    'name', // string
    'age'   => 'int;an int option;required', // set required
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
    'age'   => 'int;an int option;required', // set required
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

-----------

## Get Value

Get flag value is very simple, use method `getOpt(string $name)` `getArg($nameOrIndex)`.

> TIP: Will auto format input value by define type.

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

-----------

## Flag Rule

The options/arguments rules. Use rule can quick define an option or argument.

- string value is rule(`type;desc;required;default;shorts`).
- array is define item `SFlags::DEFINE_ITEM`
- supportted type see `FlagType::*`

```php
use Toolkit\PFlag\FlagType;

$rules = [
     // v: only value, as name and use default type FlagType::STRING
     // k-v: key is name, value can be string|array
     'long,s',
     // name => rule
     'long,a,b' => 'int', // long is option name, a and b is shorts.
     'f'      => FlagType::BOOL,
     'str1'   => ['type' => 'int', 'desc' => 'an string option'],
     'tags'   => 'array', // can also: ints, strings
     'name'   => 'type;the description message;required;default', // with desc, default, required
]
```

**For options**

- option allow set shorts

> TIP: name `long,a,b` - `long` is the option name. remaining `a,b` is short names.

**For arguments**

- arguemnt no alias/shorts
- array value only allow defined at last

**Definition item**

The const `Flags::DEFINE_ITEM`:

```php
public const DEFINE_ITEM = [
    'name'      => '',
    'desc'      => '',
    'type'      => FlagType::STRING,
    'helpType'  => '', // use for render help
    // 'index'    => 0, // only for argument
    'required'  => false,
    'default'   => null,
    'shorts'    => [], // only for option
    // value validator
    'validator' => null,
    // 'category' => null
];
```

-----------

## Costom settings

### Settings for parse

```php
    // -------------------- settings for parse option --------------------

    /**
     * Stop parse option on found first argument.
     *
     * - Useful for support multi commands. eg: `top --opt ... sub --opt ...`
     *
     * @var bool
     */
    protected $stopOnFistArg = true;

    /**
     * Skip on found undefined option.
     *
     * - FALSE will throw FlagException error.
     * - TRUE  will skip it and collect as raw arg, then continue parse next.
     *
     * @var bool
     */
    protected $skipOnUndefined = false;

    // -------------------- settings for parse argument --------------------

    /**
     * Whether auto bind remaining args after option parsed
     *
     * @var bool
     */
    protected $autoBindArgs = true;

    /**
     * Strict match args number.
     * if exist unbind args, will throw FlagException
     *
     * @var bool
     */
    protected $strictMatchArgs = false;

```

### Setting for render help

support some settings for render help

```php

    // -------------------- settings for built-in render help --------------------

    /**
     * Auto render help on provide '-h', '--help'
     *
     * @var bool
     */
    protected $autoRenderHelp = true;

    /**
     * Show flag data type on render help.
     *
     * if False:
     *
     * -o, --opt    Option desc
     *
     * if True:
     *
     * -o, --opt STRING   Option desc
     *
     * @var bool
     */
    protected $showTypeOnHelp = true;

    /**
     * Will call it on before print help message
     *
     * @var callable
     */
    private $beforePrintHelp;

```

- custom help message renderer

```php
$fs->setHelpRenderer(function (\Toolkit\PFlag\FlagsParser $fs) {
    // render help messages
});
```

-----------

## Unit tests

```bash
phpunit --debug
```

test with coverage:

```bash
phpdbg -qrr $(which phpunit) --coverage-text
phpdbg -qrr $(which phpunit) --coverage-clover ./test/clover.info
```

## Project use

Check out these projects, which use https://github.com/php-toolkit/pflag :

- [inhere/console](https://github.com/inhere/console) Full-featured php command line application library.
- [kite](https://github.com/inhere/kite) Kite is a tool for help development.
- More, please see [Packagist](https://packagist.org/packages/toolkit/pflag)

## License

[MIT](LICENSE)

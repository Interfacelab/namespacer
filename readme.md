# Namespacer
Using composer when building WordPress plugins sound like a good idea at first, but it won't be long until you run into 
trouble when other plugins are loading incompatible versions of libraries that you are using.  Our plugin, 
[MediaCloud](https://mediacloud.press), uses packages extensively - over 65 different libraries - many of them fairly 
common.  I'd say over half of our support requests are due to conflicts with other plugins users have installed that 
are using outdated or newer versions of the same libraries we are using.  The only way to really do that is to 
re-namespace the packages we are using and to be able to do that easily and safely.

Namespacer allows you to re-namespace any composer packages you are using by adding a namespace prefix to all of the 
namespaces as well as prefixing the package names too.  It will then generate a folder called "lib" which you can safely
include in your plugin.

Yes, I'm aware of projects out there like PHP-Scoper and Imposter and others.  I've had issues with all of them, which 
is why I built this.  Yoast and other plugins happily used PHP-Scoper, but their plugins, believe it or not, are much
smaller than [MediaCloud](https://mediacloud.press).  Yoast, for example, uses 4 composer packages.

## Installation
You can install this globally, but I think you'd be better off using it as the basis of a project via composer.

```bash
composer require ilab/namespacer
```

## Usage
Once installed:

```bash
./vendor/bin/renamespace [--composer COMPOSER] [--source SOURCE] [--package PACKAGE] [--namespace NAMESPACE] [--config CONFIG] <dest>
```

### Arguments
| Argument    | Description |
| ----------- | ----------- |
| `composer`  | The path (full or relative) to the composer file containing all the package dependencies you want to renamespace.  You must specify this argument OR the `source` argument. |
| `source`    | The path (full or relative) to a directory containing a composer file and an existing vendor directory.  When using `source` the vendor directory must already exist (`composer update` must already have been run).  You must specify this argument OR the `composer` argument. |
| `package`   | The prefix to append to package names, for example specifying `--package mcloud` will turn `nesbot/carbon` into `mcloud-nesbot/carbon`. Default is `mcloud`. |
| `namespace` | The prefix to append to namespaces, for example specifying `--namespace MediaCloud\Vendor` will transform `namespace Aws;` into `namespace MediaCloud\Vendor\Aws;`. Default is `MediaClound\Vendor`. |
| `config`    | An optional PHP configuration file for inserting filters into the namespacing process. |
| `<dest>`    | The destination directory.  Namespacer will create a directory named `lib` inside that directory, removing it first if it already exists. |

For example, you might run it:

```bash
./vendor/bin/namespacer --composer sample.composer.json --config patches.config.php --package mypackage --namespace MyNamespace\Vendor build/
```

In this example, we're pointing to a `composer.json` file containing the packages we want to re-namespace and to a 
config file that contains filters that will apply more manual patches during the re-namespace process.  The output 
of the processing will be put into the `build/` folder.

### Filtering (Patching in PHP-Scoper parlance)
You can see some example configurations in `vendor/ilab/namespacer/sample.config.php` and 
`vendor/ilab/namespacer/patches.config.php` that will demonstrate how to insert your own code into the namespacing
process to catch special cases. 

##Reporting Bugs
If you run into issues, please open a ticket and attach the composer.json you were trying to process with a clear
description of the problem.
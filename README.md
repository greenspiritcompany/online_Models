# Shared models and data mapper repository

This repository contains data models and data mappers that populate the models. This library is shared using Composer, reducing the amount of duplicated code across projects sharing the same data sources.

## Running local unit tests
```
$ composer update
$ ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests/
```
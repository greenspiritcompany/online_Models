# Shared models and data mapper repository

This repository contains data models and data mappers that populate the models. This library is shared using Composer, reducing the amount of duplicated code across projects sharing the same data sources.


## Unit Tests Setup

- Start a local MySQL server with a empty database named `phpunit_model_tests`
- Install dependencies with `composer update`

Configuration is located in `phpunit.xml`

## Running Unit Tests
```
$ ./vendor/bin/phpunit
```
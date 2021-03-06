# Laravel Package for handling Discounts used in Ecommerce packages

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tipoff/discounts.svg?style=flat-square)](https://packagist.org/packages/tipoff/discounts)
![Tests](https://github.com/tipoff/discounts/workflows/Tests/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/tipoff/discounts.svg?style=flat-square)](https://packagist.org/packages/tipoff/discounts)

## Installation

You can install the package via composer:

```bash
composer require tipoff/discounts
```

The migrations will run from the package. You can extend the Models from the package if you need additional classes or functions added to them.

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Tipoff\Discounts\DiscountsServiceProvider" --tag="discounts-config"
```

## Models

We include the following model:

**List of Models**

- Discount

For each of these models, this package implements an [authorization policy](https://laravel.com/docs/8.x/authorization) that extends the roles and permissions approach of the [tipoff/authorization](https://github.com/tipoff/authorization) package. The policies for each model in this package are registered through the package and do not need to be registered manually.

The models also have [Laravel Nova resources](https://nova.laravel.com/docs/3.0/resources/) in this package and they are also registered through the package and do not need to be registered manually.

#### Registering the Nova resources

If you would like to use the Nova resources included with this package, you need to register it manually in your `NovaServiceProvider` in the `boot` method.

```php
Nova::resources([
    \Tipoff\Discounts\Nova\Discount::class,
]);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Tipoff](https://github.com/tipoff)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

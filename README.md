# This is my package laravel-vector-search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/1568086-miguelenes/laravel-vector-search.svg?style=flat-square)](https://packagist.org/packages/1568086-miguelenes/laravel-vector-search)
[![GitHub Tests Action Status](https://github.com/spatie/package-laravel-vector-search-laravel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/1568086-miguelenes/laravel-vector-search/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/package-laravel-vector-search-laravel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/1568086-miguelenes/laravel-vector-search/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/1568086-miguelenes/laravel-vector-search.svg?style=flat-square)](https://packagist.org/packages/1568086-miguelenes/laravel-vector-search)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-vector-search.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-vector-search)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require 1568086-miguelenes/laravel-vector-search
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-vector-search-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-vector-search-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-vector-search-views"
```

## Usage

```php
$laravelVectorSearch = new Eloquage\LaravelVectorSearch();
echo $laravelVectorSearch->echoPhrase('Hello, Eloquage!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](https://github.com/eloquage/laravel-vector-search/security/policy) on how to report
security vulnerabilities.

## Credits

- [Miguel Enes](https://github.com/1568086+miguelenes)
- [All Contributors](https://github.com/eloquage/laravel-vector-search/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

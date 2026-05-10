# Treasurer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/th3jk/treasurer.svg?style=flat-square)](https://packagist.org/packages/th3jk/treasurer)
[![Tests](https://img.shields.io/github/actions/workflow/status/th3jk/treasurer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/th3jk/treasurer/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/th3jk/treasurer.svg?style=flat-square)](https://packagist.org/packages/th3jk/treasurer)

A Laravel package that wraps multiple payment gateways behind one
gateway-agnostic API. Configure one active gateway (GoPay or Comgate) and
write your application code once — switching providers is a config change,
not a code change.

This release ships **simple one-shot payments and refunds** for **GoPay** and
**Comgate**. The architecture is designed so that more providers (Stripe,
Adyen, …) and richer flows (recurring/subscription, preauth, webhooks) slot
in as non-breaking additions.

## Documentation

Full docs live in the [docs/](docs) folder:

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Usage](docs/usage.md)
- [Architecture](docs/architecture.md)
- [Extending — adding a custom gateway](docs/extending.md)

## Quick example

```php
use Th3JK\Treasurer\Facades\Treasurer;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\Enums\{Currency, Language};

$payment = Treasurer::pay(new PaymentRequest(
    referenceId: 'ORDER-1001',
    amountInCents: 19900,
    currency: Currency::CZK,
    description: 'Premium plan',
    language: Language::CS,
    returnUrl: route('payments.return'),
    notificationUrl: route('payments.webhook'),
));

return redirect($payment->redirect_url);
```

## Installation

```bash
composer require th3jk/treasurer
php artisan vendor:publish --tag=treasurer-config
php artisan migrate
```

Set `TREASURER_PROVIDER` and the corresponding credentials in `.env` — see
[configuration](docs/configuration.md).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [th3jk](https://github.com/th3jk)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

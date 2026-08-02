# Zana Core

Core runtime for the Zana framework.

## Features

- **`Application`** — composition root and lifecycle manager (boot, run, terminate)
- **`ApplicationInterface`** — application contract
- **`LoadEnvironment`** — loads `.env` files to set environment variables
- **`LifecycleManager`** — lifecycle orchestration
- **`ServiceProvider`** — base class for registering services with the container
- **`ApplicationException`** — typed error handling

## Install

```
composer require zana/core
```

## Usage

```php
use Zana\Core\Application;

$app = new Application(
    basePath: __DIR__,
    environment: 'development',
    debug: true
);

$app->loadConfiguration();
$app->register(new MyServiceProvider());
$app->boot();
```

## Testing

```
vendor/bin/phpunit packages/core/tests
```

## License

MIT — see [LICENSE](LICENSE).


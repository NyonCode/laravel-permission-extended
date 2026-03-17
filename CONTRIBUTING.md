# Contributing

Contributions are welcome! Please follow these guidelines.

## Development Setup

```bash
git clone git@github.com:NyonCode/laravel-permission-extended.git
cd laravel-permission-extended
composer install
```

## Running Tests

```bash
composer test                  # all tests
composer test -- --filter=edge # specific tests
composer test-coverage         # with coverage report
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint):

```bash
composer format     # fix style
vendor/bin/pint --test  # check only
```

## Static Analysis

```bash
composer analyse
```

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Ensure code style passes (`composer format`)
6. Ensure static analysis passes (`composer analyse`)
7. Commit with a clear message
8. Push and open a Pull Request

## Reporting Bugs

Please use [GitHub Issues](https://github.com/NyonCode/laravel-permission-extended/issues) and include:

- PHP and Laravel version
- spatie/laravel-permission version
- Steps to reproduce
- Expected vs actual behavior

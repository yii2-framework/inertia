# Testing

This package contains a PHP test suite with mutation testing support.

## Automated refactoring and coding standards

Run Rector:

```bash
composer rector
```

Run Easy Coding Standard with fixes:

```bash
composer ecs
```

## Dependency definition check

Verify runtime dependency declarations:

```bash
composer check-dependencies
```

## Mutation testing

Run mutation testing:

```bash
composer mutation
```

Run mutation testing with static analysis enabled:

```bash
composer mutation-static
```

## PHP test suite

Run the PHP unit and integration tests:

```bash
composer tests
```

This executes the PHPUnit suite defined in `phpunit.xml.dist`.

## Static analysis

Run PHPStan:

```bash
composer static
```

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Examples:

```bash
composer tests -- --filter ManagerTest
composer static -- --memory-limit=512M
```

## Next steps

- 📚 [Installation Guide](installation.md)
- ⚙️ [Configuration Reference](configuration.md)
- 💡 [Usage Examples](examples.md)

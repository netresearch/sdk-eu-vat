# Contributing to EU VAT SOAP SDK

Thank you for your interest in contributing to the EU VAT SOAP SDK! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for all contributors.

## How to Contribute

### Reporting Issues

1. **Check existing issues** - Before creating a new issue, please check if it already exists
2. **Use issue templates** - Follow the provided templates for bug reports and feature requests
3. **Provide details** - Include:
   - SDK version
   - PHP version
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - Error messages and stack traces

### Submitting Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow coding standards** - All code must pass:
   - PHPStan level 8
   - PSR-12 via PHP_CodeSniffer
   - All existing tests
3. **Add tests** - Include tests for new features or bug fixes
4. **Update documentation** - Keep README, PHPDoc, and examples current
5. **Sign commits** - Use `git commit -s` to sign the Developer Certificate of Origin

### Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/sdk-eu-vat.git
cd sdk-eu-vat

# Install dependencies
composer install

# Run tests
composer test

# Run quality checks
composer cs:check
composer analyse
```

## Coding Standards

### PHP Standards

- **PHP 8.1+** - Use modern PHP features
- **Strict types** - All files must declare `strict_types=1`
- **Type declarations** - Use property types, parameter types, and return types
- **PSR-12** - Follow PSR-12 coding style

### Financial Calculations

- **Always use BigDecimal** - Never use float for monetary values
- **Maintain precision** - Ensure calculations preserve decimal precision
- **Document precision** - Clearly document rounding behavior

### Error Handling

- **Use domain exceptions** - Throw appropriate exception types
- **Clear messages** - Provide actionable error messages
- **No sensitive data** - Never expose internal paths or credentials

## Testing

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests
composer test:integration

# With coverage
composer test:coverage
```

### Writing Tests

- **Test behavior, not implementation** - Focus on public APIs
- **Use descriptive names** - Test methods should explain what they test
- **One assertion per test** - Keep tests focused
- **Mock external services** - Use VCR cassettes for SOAP calls

## Documentation

### PHPDoc Standards

```php
/**
 * Brief description of the method
 *
 * Detailed description with context and usage information.
 *
 * @param string $param Description of parameter
 * @return string Description of return value
 * @throws InvalidRequestException When validation fails
 *
 * @example
 * $result = $object->method('value');
 */
```

### Updating Examples

When adding features, update relevant examples in the `examples/` directory:

1. Keep examples runnable and self-contained
2. Include error handling
3. Add explanatory comments
4. Test the example before committing

## Release Process

### Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** - Incompatible API changes
- **MINOR** - New functionality (backwards compatible)
- **PATCH** - Bug fixes (backwards compatible)

### Creating a Release

1. Update `CHANGELOG.md` with release notes
2. Update version numbers in documentation
3. Create a signed tag: `git tag -s v1.0.0`
4. Push tag: `git push origin v1.0.0`

## Quality Checklist

Before submitting a PR, ensure:

- [ ] All tests pass (`composer test`)
- [ ] PHPStan passes (`composer analyse`)
- [ ] Code style is correct (`composer cs:check`)
- [ ] Documentation is updated
- [ ] Examples work correctly
- [ ] CHANGELOG.md is updated
- [ ] Commits are signed
- [ ] PR description explains changes

## Getting Help

- **Documentation** - Check the README and examples
- **Issues** - Search existing issues or create a new one
- **Discussions** - Use GitHub Discussions for questions

## Recognition

Contributors will be recognized in:
- Release notes
- CHANGELOG.md
- Project documentation

Thank you for contributing to the EU VAT SOAP SDK!
# Contributing to Unblock Firewall Manager

First off, thank you for considering contributing to Unblock! It's people like you that make Unblock such a great tool for the hosting community.

## Code of Conduct

By participating in this project, you are expected to uphold our Code of Conduct:

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on what is best for the community
- Show empathy towards other community members

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**

```markdown
**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. See error

**Expected behavior**
What you expected to happen.

**Environment:**
 - OS: [e.g. Ubuntu 22.04]
 - PHP Version: [e.g. 8.3.1]
 - Laravel Version: [e.g. 12.0]
 - Unblock Version: [e.g. 1.0.0]

**Additional context**
Add any other context about the problem here.
```

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Use a clear and descriptive title**
- **Provide a detailed description** of the suggested enhancement
- **Explain why this enhancement would be useful** to most Unblock users
- **List some other tools where this enhancement exists**, if applicable

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the coding standards** (see below)
3. **Add tests** if you've added code that should be tested
4. **Ensure the test suite passes**: `composer test`
5. **Make sure your code lints**: `composer pint`
6. **Run static analysis**: `composer phpstan`
7. **Write clear commit messages** (see commit message guidelines)
8. **Update documentation** if you've changed functionality
9. **Create a Pull Request** with a clear title and description

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/unblock.git
cd unblock

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

# Run tests
composer test

# Run code quality checks
composer check-full
```

## Coding Standards

### PHP Code Style

We use Laravel Pint for code styling:

```bash
# Check code style
composer pint-test

# Fix code style
composer pint
```

### PHPStan

We maintain PHPStan level max:

```bash
composer phpstan
```

### Testing

- **Write tests** for all new features
- **Maintain coverage** above 85%
- **Use Pest PHP** for all tests
- **Follow naming conventions**: `test it does something` or `test method name does something`
- **Keep tests isolated** and independent

```bash
# Run specific test
php artisan test --filter=UnblockIpActionTest

# Run with coverage
composer test:coverage

# Run parallel
php artisan test --parallel
```

### Naming Conventions

#### Classes
- **Models**: Singular, PascalCase (e.g., `User`, `Host`, `Hosting`)
- **Controllers**: PascalCase + `Controller` suffix (e.g., `ReportController`)
- **Actions**: PascalCase + `Action` suffix (e.g., `CheckFirewallAction`)
- **Services**: PascalCase + `Service` suffix (e.g., `FirewallService`)
- **Tests**: Match class name + `Test` suffix (e.g., `UserTest`)

#### Methods
- **camelCase** for all methods
- **Test methods**: `test_it_does_something()` or `it_does_something()` (Pest)
- **Boolean returns**: prefix with `is`, `has`, `should`, `can`

#### Variables
- **camelCase** for variables
- **UPPER_CASE** for constants
- **Descriptive names** (avoid abbreviations unless common)

### Database

- **Use migrations** for all schema changes
- **Use factories** for test data
- **Use seeders** only for essential data
- **Follow Laravel conventions** for table and column names

### Git Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only changes
- `style`: Code style changes (formatting, missing semi-colons, etc)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**

```bash
feat(firewall): add support for CloudLinux LVE analysis

fix(auth): resolve session timeout issue for authorized users

docs(readme): update installation instructions

test(firewall): add tests for ModSecurity JSON parsing
```

## Project Structure

```
app/
├── Actions/           # Business logic actions
├── Console/           # Artisan commands
├── Exceptions/        # Custom exceptions
├── Filament/          # Filament admin resources
├── Http/              # Controllers, middleware, requests
├── Jobs/              # Queue jobs
├── Mail/              # Mailable classes
├── Models/            # Eloquent models
├── Policies/          # Authorization policies
└── Services/          # Business logic services

config/                # Configuration files
database/
├── factories/         # Model factories
├── migrations/        # Database migrations
└── seeders/           # Database seeders

resources/
├── views/             # Blade templates
├── js/                # JavaScript files
└── css/               # CSS files

tests/
├── Feature/           # Feature tests
└── Unit/              # Unit tests
```

## Documentation

- Update `README.md` for feature changes
- Add `docs/*.md` for detailed documentation
- Use PHPDoc for all classes and public methods
- Add inline comments for complex logic
- Update `.env.example` for new configuration

## Language Translations

All user-facing text must be translatable:

```php
// Good
__('firewall.messages.ip_unblocked')

// Bad
'IP unblocked successfully'
```

Add translations to both `lang/en/` and `lang/es/` directories.

## Security

- **Never commit** `.env` files
- **Use environment variables** for sensitive data
- **Sanitize all inputs** before processing
- **Validate IP addresses** before firewall operations
- **Use prepared statements** for database queries
- Report security vulnerabilities privately to the maintainers

## Questions?

Don't hesitate to ask questions in:
- [GitHub Discussions](https://github.com/AichaDigital/unblock/discussions)
- [GitHub Issues](https://github.com/AichaDigital/unblock/issues) (for bug reports)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.


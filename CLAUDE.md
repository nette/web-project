# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Nette 3.2 Web Project** - a PHP web application skeleton using:
- **Backend:** PHP 8.2+, Nette 3.2 framework, Latte 3.0 templating
- **Database:** Nette Database Explorer (default: SQLite in-memory)
- **Frontend:** Vite 6.3+ with TypeScript support, nette-forms for client-side validation
- **Testing:** Nette Tester for unit tests
- **Static Analysis:** PHPStan level 5

## Essential Commands

### Frontend Build
```bash
# Install dependencies (first time)
npm install

# Start Vite dev server with hot reload
npm run dev

# Build production assets
npm run build
```

**Note:** Vite is optional. To activate:
1. Uncomment `type: vite` in `config/common.neon` under `assets.mapping.default`
2. Run `npm install && npm run build`

### Backend Testing
```bash
# Run all tests
composer run tester

# Run specific test file
vendor/bin/tester tests/path/to/file.phpt -s

# Run tests in specific directory
vendor/bin/tester tests/directory/ -s
```

### Code Quality
```bash
# Run PHPStan static analysis
composer run phpstan

# Analyze specific files
vendor/bin/phpstan analyse app/Presentation/Home/
```

## Architecture

### Application Entry Point Flow

1. **HTTP Request** → `/www/index.php`
2. **Bootstrap** (`App\Bootstrap::bootWebApplication()`)
   - Initializes environment and Tracy debugger
   - Loads configuration from `config/common.neon` and `config/services.neon`
   - Creates DI container
3. **Router** (`App\Core\RouterFactory::createRouter()`)
   - Default route: `<presenter>/<action>[/<id>]` → `Home:default`
4. **Presenter** processes request and renders Latte template
5. **Response** sent to client

### Directory Structure

```
app/
├── Bootstrap.php              # Application initialization
├── Core/
│   └── RouterFactory.php     # URL routing configuration
└── Presentation/             # UI layer (MVC Controllers & Views)
    ├── @layout.latte         # Master layout template
    ├── Accessory/
    │   └── LatteExtension.php # Application-wide template filters & functions
    ├── Home/
    │   ├── HomePresenter.php  # Presenter (Controller)
    │   └── default.latte      # Template (View)
    └── Error/                 # Error handling presenters
        ├── Error4xx/          # Client errors (403, 404, 410)
        └── Error5xx/          # Server errors (500, 503)
```

### Configuration Files

**`config/common.neon`** - Framework configuration:
- Application settings (error presenters, presenter mapping)
- Database connection (currently SQLite in-memory)
- Latte configuration (strict types & parsing)
- Assets configuration (Vite integration)

**`config/services.neon`** - Dependency injection:
- Manual service registration
- Auto-discovery for classes ending with: `*Facade`, `*Factory`, `*Repository`, `*Service`

### Presenter-Template Mapping

Convention: `App\Presentation\<Module>\<Presenter>Presenter`

Examples:
- `Home:default` → `App\Presentation\Home\HomePresenter::renderDefault()` + `Home/default.latte`
- `Error:Error4xx` → `App\Presentation\Error\Error4xx\Error4xxPresenter`

### Error Handling Architecture

**Client Errors (4xx):** `Error:Error4xx` presenter
- Renders appropriate template based on HTTP code (403.latte, 404.latte, 410.latte, or 4xx.latte fallback)

**Server Errors (5xx):** `Error:Error5xx` presenter
- Logs exception via Tracy
- Renders minimal error page (500.phtml, 503.phtml)

Configuration in `config/common.neon`:
```neon
application:
	errorPresenter:
		4xx: Error:Error4xx
		5xx: Error:Error5xx
```

### Asset Pipeline

**Development mode:**
- Source files in `assets/` directory
- Vite dev server provides hot module replacement
- Entry point: `assets/main.js` (initializes Nette Forms)

**Production mode:**
- Built assets in `www/assets/` with versioned filenames
- Manifest file for asset resolution
- Load in templates: `{asset 'main.js'}`

**Vite configuration** (`vite.config.ts`):
- Entry point: `main.js`
- CSS source maps enabled for development
- `emptyOutDir: true` clears output before build

### Service Auto-Discovery

The DI container automatically registers classes matching these patterns:
- `*Facade` - Application facades
- `*Factory` - Factory classes
- `*Repository` - Data repositories
- `*Service` - Service classes

Located anywhere under `app/` directory (configured in `config/services.neon`).

### Latte Template System

**Master Layout:** `app/Presentation/@layout.latte`
- Shared structure for all pages
- Includes Tracy debugger bar in debug mode

**Template Extension:** `App\Presentation\Accessory\LatteExtension`
- Application-wide custom filters and functions
- Registered in `config/common.neon`

**Strict Mode:** Enabled via `latte.strictParsing`
- All variables must be explicitly defined
- Type safety in templates

## Development Notes

### Adding New Presenters

1. Create presenter class: `app/Presentation/<Module>/<Name>Presenter.php`
2. Create template: `app/Presentation/<Module>/<action>.latte`
3. Services are auto-registered if following naming conventions

### Database Configuration

Default is SQLite in-memory for quick prototyping. To use persistent database:

```neon
# config/common.neon
database:
	dsn: 'mysql:host=127.0.0.1;dbname=mydb'
	user: username
	password: password
```

### Testing Conventions

- Test files use `.phpt` extension
- Bootstrap: `tests/bootstrap.php`
- Nette Tester documentation: https://tester.nette.org/

### Security

- Directories `app/`, `config/`, `log/`, `temp/` must NOT be web-accessible
- `.htaccess` blocks access to hidden files and sensitive paths
- Tracy debugger should be disabled in production

### PHPStan Configuration

**Current level:** 5 (strict analysis)
**Analyzed paths:** `src`, `bin`, `tests`

Note: `phpstan.neon` references `src/` directory but project uses `app/`. This may need correction if adding actual source code to analyze.

### Adding Nette Packages

To add additional Nette packages to the project:

```bash
# Find the package you need at https://nette.org/packages
# Install using Composer
composer require nette/package-name

# Example: Adding Nette Utils
composer require nette/utils
```

## NEON Configuration Reference

Configuration files use NEON format. Available configuration sections in `config/common.neon`:

- **`application`** - Application settings (error presenters, mapping, routing)
- **`assets`** - Asset management and Vite integration
- **`constants`** - Definition of PHP constants
- **`database`** - Database connection configuration
- **`decorator`** - Service decoration rules
- **`di`** - DI Container settings
- **`extensions`** - Installation of additional DI extensions
- **`forms`** - Forms configuration
- **`http`** - HTTP headers configuration
- **`includes`** - Including additional configuration files
- **`latte`** - Latte template engine settings
- **`mail`** - Mailing configuration
- **`parameters`** - Custom parameters accessible via `%paramName%`
- **`php`** - PHP configuration options (date.timezone, etc.)
- **`routing`** - Alternative routing configuration
- **`search`** - Automatic service registration patterns
- **`security`** - Access control and authentication
- **`services`** - Manual service definitions
- **`session`** - Session configuration
- **`tracy`** - Tracy debugger settings

### NEON Syntax Notes

**Escaping percent sign:** To write a string containing `%`, escape it by doubling:

```neon
parameters:
	discount: '15%%'  # Results in "15%"
	url: 'https://example.com?discount=50%%'
```

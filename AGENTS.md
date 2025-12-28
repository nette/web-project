# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

This is a fresh **Nette Web Project** skeleton, a clean starting point for a new PHP
web application. Out of the box it does almost nothing: just the directory structure,
configuration and a single welcome page. You and the developer build the rest on top
of it.

## First: equip your agent

This skeleton is meant to be developed hand-in-hand with an AI agent. To do that
well, the agent needs deep, up-to-date knowledge of the Nette ecosystem (its
conventions, APIs and gotchas) plus automatic validation of the files it writes.
That knowledge ships as a set of **skills**, not baked into this file.

**Install them before writing any real code.** If the developer has not done so yet,
ask them to run the steps below first.

### Claude Code

Add the Nette marketplace (with auto-update), then install the plugins:

```
/plugin marketplace add nette/claude-code
/plugin install nette@nette
```

Strongly recommended. Validates PHP, Latte, NEON, JSON and JS after every edit and
reports errors straight back to the agent:

```
/plugin install nette-lint@nette
```

Optional, for automatic PHP code-style fixing:

```
/plugin install php-fixer@nette
/install-php-fixer
```

Details and the full skill list: https://github.com/nette/claude-code

### Other agents

The plugins above are Claude Code specific. For any other agent, the authoritative
source is the official manual at https://doc.nette.org. Consult it for the area you
are working on (architecture, forms, database, Latte, NEON, Tracy) before editing,
rather than relying on this file. This file is a signpost, not the manual.

## Project at a glance

- **Backend:** PHP 8.2+, Nette 3.2, Latte 3 templates
- **Database:** Nette Database Explorer (default: SQLite in-memory; change it in `config/common.neon`)
- **Frontend:** Vite + TypeScript, `nette-forms` for client-side validation (Vite is optional, see `readme.md`)
- **Testing:** Nette Tester (`.phpt`)
- **Static analysis:** PHPStan level 8 (`app`, `bin`)

```
app/
├── Bootstrap.php              # Application initialization
├── Core/RouterFactory.php     # URL routing
└── Presentation/              # Presenters + Latte templates
    ├── @layout.latte
    ├── Home/                  # Home:default, the welcome page
    └── Error/                 # 4xx / 5xx error presenters
config/
├── common.neon                # framework configuration
└── services.neon              # DI services + auto-discovery
www/index.php                  # HTTP entry point
```

## Essential commands

```bash
# Run the app (built-in server)
php -S localhost:8000 -t www

# Tests
composer run tester
vendor/bin/tester tests/path/to/file.phpt -s

# Static analysis
composer run phpstan

# Frontend (only if Vite is enabled)
npm install && npm run dev      # dev server with hot reload
npm run build                   # production assets
```

## Ground rules

- **Directories `app/`, `config/`, `log/`, `temp/` must never be web-accessible.**
  Only `www/` is the document root. See https://nette.org/security-warning.
- Tracy debugger is enabled in development and must stay off in production.
- Add Nette packages as you need them: `composer require nette/<package>`
  (catalog at https://nette.org/packages).

When in doubt about a Nette convention, defer to the installed skills. They are the
source of truth, and they are kept current in a way this file is not.

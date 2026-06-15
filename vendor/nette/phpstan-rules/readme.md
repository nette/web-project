# PHPStan extensions for Nette libraries

![Nette PHPStan Rules](https://github.com/user-attachments/assets/c231ed47-a413-4dd2-81ef-83e52c080427)

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/phpstan-rules.svg)](https://packagist.org/packages/nette/phpstan-rules)
[![Tests](https://github.com/nette/phpstan-rules/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/phpstan-rules/actions)
[![Latest Stable Version](https://poser.pugx.org/nette/phpstan-rules/v/stable)](https://github.com/nette/phpstan-rules/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/phpstan-rules/blob/master/license.md)

 <!---->

<h3>

Makes [PHPStan](https://phpstan.org) smarter about Nette code. Install, and it just works — more precise types, fewer false positives.

</h3>

 <!---->


## Installation

Install via Composer:

```shell
composer require --dev nette/phpstan-rules
```

Requirements: PHP 8.1 or higher and PHPStan 2.1+.

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is registered automatically. Otherwise add to your `phpstan.neon`:

```neon
includes:
    - vendor/nette/phpstan-rules/extension.neon
```

 <!---->

## What's Included

 <!---->

**Precise return types** — narrows return types of `Strings::match()`, `matchAll()`, `split()`, `Helpers::falseToNull()`, `Expect::array()`, `Arrays::invoke()`, and `Arrays::invokeMethod()` based on the arguments you pass. For `Strings::match()` and `matchAll()` with a constant pattern, the exact array shape is derived from the regular expression — e.g. `Strings::match($s, '#(\d+)-(\w+)#')` returns `array{string, string, string}|null` with one element per capture group (named groups included). The same shape is inferred for the `$matches` argument of a `Strings::replace()` callback. Also narrows `Container::getComponent()` and `$container['...']` to match the corresponding `createComponent*()` factory return type. For forms, `$form['name']` returns the specific control type (e.g. `TextInput`, `SelectBox`) based on the `addText()`, `addSelect()`, etc. call in the same function.

**Database row mapping** — narrows return types of `Explorer::table()`, `ActiveRow::related()`, `ActiveRow::ref()`, and `Selection::insert()` based on a configurable table-to-entity-class convention. For example, `$explorer->table('booking')` returns `Selection<BookingRow>` instead of `Selection<ActiveRow>`, and a single-row `$bookings->insert([...])` returns `BookingRow` instead of the wide schema-dependent union. Configure via:

```neon
parameters:
    nette:
        database:
            mapping:
                tables:
                	*: App\Entity\*Row                    # * = PascalCase table name
                    special_table: App\Entity\SpecialRow  # optional explicit overrides
```

**Asset type narrowing** — narrows return types of `Registry::getMapper()` to the specific mapper class, and `Registry::getAsset()` / `tryGetAsset()` to the specific asset type (e.g. `ImageAsset`, `ScriptAsset`) based on file extension. Also narrows `FilesystemMapper::getAsset()` and `ViteMapper::getAsset()` directly. Configure via:

```neon
parameters:
    nette:
        assets:
            mapping:
                default: file                   # FilesystemMapper
                images: file                    # FilesystemMapper
                vite: vite                      # ViteMapper
                custom: App\MyMapper            # custom class (FQCN)
```

**Html magic methods** — resolves `$html->getXxx()`, `setXxx()`, and `addXxx()` calls on `Nette\Utils\Html` that go through `__call()` but aren't declared via `@method` annotations.

**Removes `|false` and `|null` from PHP functions** — many native functions like `getcwd`, `json_encode`, `preg_split`, `preg_replace`, and [many more](extension-php.neon) include `false` or `null` in their return type even though these error values are unrealistic on modern systems.

**Injected properties** — properties marked with the `#[Nette\DI\Attributes\Inject]` attribute are treated as initialized and written, so PHPStan won't report them as uninitialized or never written.

**Invalid regex detection** — reports invalid regular expression patterns passed to `Strings::match()`, `matchAll()`, `split()`, and `replace()`, so a malformed pattern is caught during analysis instead of at runtime.

**AbortException safety** — warns when a broad `catch (\Throwable)` or `catch (\Exception)` in a try block that calls `redirect()`, `forward()`, `terminate()`, etc. swallows `Nette\Application\AbortException` instead of rethrowing it — a mistake that silently breaks redirects.

**Subject narrowing after match** — inside `if (Strings::match($s, '#\d+#'))` the subject `$s` is narrowed (e.g. to `non-empty-string`) based on the pattern.

**Assert type narrowing** — PHPStan understands type guarantees after `Tester\Assert` calls like `notNull()`, `type()`, `true()`, etc.

**False positive suppression** — silences known PHPStan false positives in Nette patterns (arrow functions passed as `void` callbacks, runtime type validation closures, callbacks assigned to Form event-handler properties like `$onSuccess`, `$onClick`).

 <!---->

### Type Assertion Testing Helper

For Nette package developers: `TypeAssert` lets you verify type inference in tests using [Nette Tester](https://tester.nette.org):

```php
use Nette\PHPStan\Tester\TypeAssert;

TypeAssert::assertTypes(__DIR__ . '/data/types.php');
TypeAssert::assertNoErrors(__DIR__ . '/data/clean.php');
```

The data file uses `assertType()` from PHPStan:

```php
use function PHPStan\Testing\assertType;

assertType('non-empty-string', getcwd());
assertType('string', Normalizer::normalize('foo'));
```

 <!---->

## [Support Me](https://github.com/sponsors/dg)

Do you like Nette? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!

<?php declare(strict_types=1);

namespace Nette\PHPStan\Database;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;


/**
 * Resolves database table names to entity row class types.
 * Uses a table-to-class map with optional '*' wildcards in keys (e.g. 'forum_*').
 * Class names may contain '*' which is replaced with PascalCase of the captured portion
 * (or the full table name for exact keys). Exact keys take precedence; wildcard entries
 * are tried in declaration order.
 */
class TableRowTypeResolver
{
	/**
	 * @param array<string, string> $tables table -> FQCN map; keys may contain a single '*' wildcard,
	 *     and a bare '*' acts as a catch-all fallback. Values may contain '*' which is replaced with
	 *     PascalCase of the captured portion.
	 */
	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly array $tables = [],
	) {
	}


	/**
	 * Resolves a table name to an ObjectType for the entity row class.
	 * Returns null if no mapping applies (class does not exist).
	 */
	public function resolve(string $tableName): ?ObjectType
	{
		$className = $this->resolveClassName($tableName);
		if ($className === null) {
			return null;
		}

		return $this->reflectionProvider->hasClass($className)
			? new ObjectType($className)
			: null;
	}


	/**
	 * Extracts the table name from a key parameter.
	 * For related()/ref(), key can be 'table' or 'table.column'.
	 */
	public function extractTableName(string $key): string
	{
		$pos = strpos($key, '.');
		return $pos !== false ? substr($key, 0, $pos) : $key;
	}


	private function resolveClassName(string $tableName): ?string
	{
		if (isset($this->tables[$tableName])) {
			return $this->expandClass($this->tables[$tableName], $tableName);
		}

		foreach ($this->tables as $pattern => $class) {
			if (!str_contains($pattern, '*')) {
				continue;
			}
			$regex = '#^' . str_replace('\*', '(.*)', preg_quote($pattern, '#')) . '$#D';
			if (preg_match($regex, $tableName, $m)) {
				return $this->expandClass($class, $m[1]);
			}
		}

		return null;
	}


	private function expandClass(string $class, string $capture): string
	{
		return str_contains($class, '*')
			? str_replace('*', $this->snakeToPascalCase($capture), $class)
			: $class;
	}


	public function snakeToCamelCase(string $name): string
	{
		return lcfirst($this->snakeToPascalCase($name));
	}


	private function snakeToPascalCase(string $name): string
	{
		return str_replace(' ', '', ucwords(strtr($name, '_', ' ')));
	}
}

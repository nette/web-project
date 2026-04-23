<?php declare(strict_types=1);

namespace Nette\PHPStan\Database;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;


/**
 * Resolves database table names to entity row class types.
 * Convention: table_name -> str_replace('*', PascalCase(table), mask).
 * Explicit overrides in $tables take precedence over convention.
 */
class TableRowTypeResolver
{
	/**
	 * @param string $convention mask like App\Entity\*Row, where * is replaced by PascalCase table name
	 * @param array<string, string> $tables explicit table -> FQCN overrides
	 */
	public function __construct(
		private ReflectionProvider $reflectionProvider,
		private string $convention = '',
		private array $tables = [],
	) {
	}


	/**
	 * Resolves a table name to an ObjectType for the entity row class.
	 * Returns null if no mapping applies (class does not exist).
	 */
	public function resolve(string $tableName): ?ObjectType
	{
		if (isset($this->tables[$tableName])) {
			$className = $this->tables[$tableName];
			return $this->reflectionProvider->hasClass($className)
				? new ObjectType($className)
				: null;
		}

		if ($this->convention === '') {
			return null;
		}

		$className = str_replace('*', $this->snakeToPascalCase($tableName), $this->convention);
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


	private function snakeToPascalCase(string $table): string
	{
		return str_replace(' ', '', ucwords(strtr($table, '_', ' ')));
	}
}

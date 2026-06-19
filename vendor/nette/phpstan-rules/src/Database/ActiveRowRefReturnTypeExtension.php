<?php declare(strict_types=1);

namespace Nette\PHPStan\Database;

use Nette\Database\Table\ActiveRow;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;


/**
 * Narrows return type of ActiveRow::ref() from ?self
 * to ?EntityRow based on table-to-entity-class mapping.
 *
 * When the foreign-key column (2nd argument) is declared as non-nullable on the
 * calling row class, the referenced row is guaranteed to exist, so the result is
 * narrowed to a non-nullable EntityRow.
 */
class ActiveRowRefReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private readonly TableRowTypeResolver $resolver,
	) {
	}


	public function getClass(): string
	{
		return ActiveRow::class;
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'ref';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		$keyType = $scope->getType($args[0]->value);
		$constantStrings = $keyType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		$key = $constantStrings[0]->getValue();
		$tableName = $this->resolver->extractTableName($key);
		$rowType = $this->resolver->resolve($tableName);
		if ($rowType === null) {
			return null;
		}

		if (isset($args[1]) && $this->isColumnNonNullable($args[1]->value, $methodCall->var, $scope)) {
			return $rowType;
		}

		return TypeCombinator::addNull($rowType);
	}


	/**
	 * Tells whether the FK column is declared as non-nullable on the calling row class.
	 * Tries both camelCase (Explorer convention) and the raw column name.
	 */
	private function isColumnNonNullable(Expr $columnExpr, Expr $callerExpr, Scope $scope): bool
	{
		$columnStrings = $scope->getType($columnExpr)->getConstantStrings();
		if (count($columnStrings) !== 1) {
			return false;
		}

		$column = $columnStrings[0]->getValue();
		$callerType = $scope->getType($callerExpr);

		foreach ([$this->resolver->snakeToCamelCase($column), $column] as $property) {
			if (!$callerType->hasProperty($property)->yes()) {
				continue;
			}
			$propertyType = $callerType->getProperty($property, $scope)->getReadableType();
			return $propertyType->isSuperTypeOf(new NullType)->no();
		}

		return false;
	}
}

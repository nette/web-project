<?php declare(strict_types=1);

namespace Nette\PHPStan\Database;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use function count;


/**
 * Narrows return type of ActiveRow::related() from GroupedSelection<ActiveRow>
 * to GroupedSelection<EntityRow> based on table-to-entity-class mapping.
 */
class ActiveRowRelatedReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private TableRowTypeResolver $resolver,
	) {
	}


	public function getClass(): string
	{
		return 'Nette\Database\Table\ActiveRow';
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'related';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
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

		return new GenericObjectType('Nette\Database\Table\GroupedSelection', [$rowType]);
	}
}

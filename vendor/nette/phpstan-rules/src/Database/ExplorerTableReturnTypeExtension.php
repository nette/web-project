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
 * Narrows return type of Explorer::table() from Selection<ActiveRow>
 * to Selection<EntityRow> based on table-to-entity-class mapping.
 */
class ExplorerTableReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private TableRowTypeResolver $resolver,
	) {
	}


	public function getClass(): string
	{
		return 'Nette\Database\Explorer';
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'table';
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

		$nameType = $scope->getType($args[0]->value);
		$constantStrings = $nameType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		$tableName = $constantStrings[0]->getValue();
		$rowType = $this->resolver->resolve($tableName);
		if ($rowType === null) {
			return null;
		}

		return new GenericObjectType('Nette\Database\Table\Selection', [$rowType]);
	}
}

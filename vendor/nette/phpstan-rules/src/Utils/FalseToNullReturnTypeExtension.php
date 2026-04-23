<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\Helpers;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;


/**
 * Narrows the return type of Helpers::falseToNull() from mixed.
 * Removes false from the argument type and adds null instead.
 */
class FalseToNullReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return Helpers::class;
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'falseToNull';
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		$argType = $scope->getType($args[0]->value);
		$falseType = new ConstantBooleanType(false);

		if ($falseType->isSuperTypeOf($argType)->no()) {
			return $argType;
		}

		$withoutFalse = TypeCombinator::remove($argType, $falseType);
		if ($withoutFalse instanceof NeverType) {
			return new NullType;
		}

		return TypeCombinator::addNull($withoutFalse);
	}
}

<?php declare(strict_types=1);

namespace Nette\PHPStan\ComponentModel;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count, in_array, ucfirst;


/**
 * Narrows return types of Container::getComponent() and Container::offsetGet()
 * based on the corresponding createComponent<Name>() factory method.
 */
class GetComponentReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'Nette\ComponentModel\Container';
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['getComponent', 'offsetGet'], true);
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

		$componentName = $constantStrings[0]->getValue();
		$factoryMethodName = 'createComponent' . ucfirst($componentName);

		$callerType = $scope->getType($methodCall->var);
		if (!$callerType->hasMethod($factoryMethodName)->yes()) {
			return null;
		}

		$factoryMethod = $callerType->getMethod($factoryMethodName, $scope);
		$returnType = $factoryMethod->getVariants()[0]->getReturnType();

		// Respect $throw parameter for getComponent()
		if ($methodReflection->getName() === 'getComponent' && count($args) >= 2) {
			$throwType = $scope->getType($args[1]->value);
			if (!$throwType->isTrue()->yes()) {
				$returnType = TypeCombinator::addNull($returnType);
			}
		}

		return $returnType;
	}
}

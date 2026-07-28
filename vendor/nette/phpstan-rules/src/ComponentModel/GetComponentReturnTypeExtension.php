<?php declare(strict_types=1);

namespace Nette\PHPStan\ComponentModel;

use Nette\ComponentModel\Container;
use Nette\PHPStan\Components\ComponentTreeResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count, explode, in_array, ucfirst;


/**
 * Narrows return types of Container::getComponent() and Container::offsetGet()
 * based on the corresponding createComponent<Name>() factory method, and supports
 * chained ($this['a']['b']) and dash ($this['a-b']) access into nested components.
 */
class GetComponentReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private readonly ComponentTreeResolver $resolver,
	) {
	}


	public function getClass(): string
	{
		return Container::class;
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
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

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
		$caller = $methodCall->var;
		$segments = explode('-', $componentName);

		if (count($segments) > 1) {
			$returnType = $this->resolver->resolveDashPath($caller, $segments, $scope);
		} elseif (!$caller instanceof Variable) {
			$returnType = $this->resolver->resolveChainedChild($caller, $componentName, $scope);
		} else {
			$factoryMethodName = 'createComponent' . ucfirst($componentName);
			$callerType = $scope->getType($caller);
			$returnType = $callerType->hasMethod($factoryMethodName)->yes()
				? $callerType->getMethod($factoryMethodName, $scope)->getVariants()[0]->getReturnType()
				: null;
		}

		// Miss must stay null so the declared type (or another extension's result) is kept
		if ($returnType === null) {
			return null;
		}

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

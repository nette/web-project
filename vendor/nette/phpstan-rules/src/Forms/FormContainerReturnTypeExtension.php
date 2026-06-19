<?php declare(strict_types=1);

namespace Nette\PHPStan\Forms;

use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\PHPStan\Components\ComponentTreeResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count, explode, in_array, is_string;


/**
 * Narrows return types of Forms\Container::getComponent() and ::offsetGet().
 * Resolves the control type from the corresponding addXxx() call (local, cross-method
 * or cross-class), and supports chained ($form['a']['b']) and dash ($this['a-b']) access.
 */
class FormContainerReturnTypeExtension implements DynamicMethodReturnTypeExtension
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
			$type = $this->resolver->resolveDashPath($caller, $segments, $scope);
		} elseif ($caller instanceof Variable && is_string($caller->name)) {
			$type = $this->resolver->resolveFromLocalVar($scope, $caller->name, $componentName)
				?? $this->resolver->resolveChildOf($scope->getType($caller), null, $componentName, $scope);
		} else {
			$type = $this->resolver->resolveChainedChild($caller, $componentName, $scope);
		}

		// Fallback: most $form['field'] accesses are controls, not containers
		$type ??= new ObjectType(BaseControl::class);

		// Respect $throw parameter for getComponent()
		if ($methodReflection->getName() === 'getComponent' && count($args) >= 2) {
			$throwType = $scope->getType($args[1]->value);
			if (!$throwType->isTrue()->yes()) {
				$type = TypeCombinator::addNull($type);
			}
		}

		return $type;
	}
}

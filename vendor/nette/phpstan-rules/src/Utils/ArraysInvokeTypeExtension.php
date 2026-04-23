<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function array_slice, count, in_array;


/**
 * Narrows return types of Arrays::invoke() and Arrays::invokeMethod()
 * by resolving callback/method return types and forwarding arguments.
 */
class ArraysInvokeTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'Nette\Utils\Arrays';
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['invoke', 'invokeMethod'], true);
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?Type
	{
		return match ($methodReflection->getName()) {
			'invoke' => $this->resolveInvoke($methodCall, $scope),
			'invokeMethod' => $this->resolveInvokeMethod($methodCall, $scope),
			default => null,
		};
	}


	private function resolveInvoke(StaticCall $call, Scope $scope): ?Type
	{
		$args = $call->getArgs();
		if ($args === []) {
			return null;
		}

		$callbacksType = $scope->getType($args[0]->value);
		$callbackType = $callbacksType->getIterableValueType();

		if (!$callbackType->isCallable()->yes()) {
			return null;
		}

		$acceptors = $callbackType->getCallableParametersAcceptors($scope);
		$forwardedArgs = array_slice($args, 1);
		$selected = ParametersAcceptorSelector::selectFromArgs($scope, $forwardedArgs, $acceptors);
		$returnType = self::voidToNull($selected->getReturnType());

		return new ArrayType($callbacksType->getIterableKeyType(), $returnType);
	}


	private function resolveInvokeMethod(StaticCall $call, Scope $scope): ?Type
	{
		$args = $call->getArgs();
		if (count($args) < 2) {
			return null;
		}

		$objectsType = $scope->getType($args[0]->value);
		$objectType = $objectsType->getIterableValueType();

		$constantStrings = $scope->getType($args[1]->value)->getConstantStrings();
		if ($constantStrings === []) {
			return null;
		}

		$forwardedArgs = array_slice($args, 2);
		$returnTypes = [];

		foreach ($constantStrings as $constantString) {
			$methodName = $constantString->getValue();
			if (!$objectType->hasMethod($methodName)->yes()) {
				return null;
			}

			$methodReflection = $objectType->getMethod($methodName, $scope);
			$selected = ParametersAcceptorSelector::selectFromArgs(
				$scope,
				$forwardedArgs,
				$methodReflection->getVariants(),
			);
			$returnTypes[] = $selected->getReturnType();
		}

		$returnType = self::voidToNull(TypeCombinator::union(...$returnTypes));
		return new ArrayType($objectsType->getIterableKeyType(), $returnType);
	}


	private static function voidToNull(Type $type): Type
	{
		return $type->isVoid()->yes() ? new NullType : $type;
	}
}

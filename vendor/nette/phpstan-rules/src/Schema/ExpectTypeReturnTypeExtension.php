<?php declare(strict_types=1);

namespace Nette\PHPStan\Schema;

use Nette\Schema\Expect;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\{DynamicStaticMethodReturnTypeExtension, ObjectType, Type as PhpStanType};
use function count;


/**
 * Narrows the return type of Expect::type() to the element a constant expression builds,
 * asking Expect itself, so the answer follows whatever the installed version returns.
 */
class ExpectTypeReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return Expect::class;
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'type';
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?PhpStanType
	{
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		$constantStrings = $scope->getType($args[0]->value)->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		try {
			$schema = Expect::type($constantStrings[0]->getValue());
		} catch (\Throwable) {
			return null; // an expression the installed version refuses
		}

		return new ObjectType($schema::class);
	}
}

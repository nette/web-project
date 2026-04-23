<?php declare(strict_types=1);

namespace Nette\PHPStan\Schema;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type as PhpStanType;


/**
 * Narrows the return type of Expect::array() from Structure|Type
 * to Structure or Type based on the argument content.
 */
class ExpectArrayReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return Expect::class;
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'array';
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?PhpStanType
	{
		$args = $methodCall->getArgs();
		if ($args === []) {
			return new ObjectType(Type::class);
		}

		$argType = $scope->getType($args[0]->value);

		if ($argType->isNull()->yes()) {
			return new ObjectType(Type::class);
		}

		$constantArrays = $argType->getConstantArrays();
		if ($constantArrays === []) {
			return null;
		}

		$valueTypes = $constantArrays[0]->getValueTypes();
		if ($valueTypes === []) {
			return new ObjectType(Type::class);
		}

		$schemaType = new ObjectType(Schema::class);
		$hasSchema = false;
		$hasNonSchema = false;

		foreach ($valueTypes as $valueType) {
			if ($schemaType->isSuperTypeOf($valueType)->yes()) {
				$hasSchema = true;
			} else {
				$hasNonSchema = true;
			}
		}

		if ($hasSchema && !$hasNonSchema) {
			return new ObjectType(Structure::class);
		}

		if ($hasNonSchema && !$hasSchema) {
			return new ObjectType(Type::class);
		}

		return null;
	}
}

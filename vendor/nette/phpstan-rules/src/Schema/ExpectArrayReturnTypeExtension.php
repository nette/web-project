<?php declare(strict_types=1);

namespace Nette\PHPStan\Schema;

use Nette\Schema\Elements\Structure;
use Nette\Schema\{Expect, Schema};
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\{DynamicStaticMethodReturnTypeExtension, ObjectType, Type as PhpStanType};


/**
 * Narrows the return type of Expect::array() by the argument content: a shape of schemas builds
 * a Structure, anything else the plain array element, which Expect::array() itself is asked for.
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
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		$args = $methodCall->getArgs();
		if ($args === []) {
			return self::getPlainArrayType();
		}

		$argType = $scope->getType($args[0]->value);

		if ($argType->isNull()->yes()) {
			return self::getPlainArrayType();
		}

		$constantArrays = $argType->getConstantArrays();
		if ($constantArrays === []) {
			return null;
		}

		$valueTypes = $constantArrays[0]->getValueTypes();
		if ($valueTypes === []) {
			return self::getPlainArrayType();
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

		if (!$hasSchema) {
			return self::getPlainArrayType();
		}

		return null;
	}


	/**
	 * The element Expect::array() builds without a shape; a plain Type up to nette/schema 1.3,
	 * an ArrayType later.
	 */
	private static function getPlainArrayType(): ?PhpStanType
	{
		try {
			return new ObjectType(Expect::array()::class);
		} catch (\Throwable) {
			return null;
		}
	}
}

<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\Strings;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function in_array;


/**
 * Narrows return types of Strings::match(), matchAll() and split()
 * based on boolean arguments like captureOffset, unmatchedAsNull, etc.
 */
class StringsReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return Strings::class;
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['match', 'matchAll', 'split'], true);
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?Type
	{
		return match ($methodReflection->getName()) {
			'match' => $this->resolveMatch($methodCall, $scope),
			'matchAll' => $this->resolveMatchAll($methodCall, $scope),
			'split' => $this->resolveSplit($methodCall, $scope),
			default => null,
		};
	}


	private function resolveMatch(StaticCall $call, Scope $scope): ?Type
	{
		$captureOffset = $this->resolveBool($call, $scope, 'captureOffset', 2);
		$unmatchedAsNull = $this->resolveBool($call, $scope, 'unmatchedAsNull', 4);
		if ($captureOffset === null || $unmatchedAsNull === null) {
			return null;
		}

		$elementType = $this->buildElementType($captureOffset, $unmatchedAsNull);
		return TypeCombinator::addNull(
			new ArrayType(new MixedType, $elementType),
		);
	}


	private function resolveMatchAll(StaticCall $call, Scope $scope): ?Type
	{
		$captureOffset = $this->resolveBool($call, $scope, 'captureOffset', 2);
		$unmatchedAsNull = $this->resolveBool($call, $scope, 'unmatchedAsNull', 4);
		$patternOrder = $this->resolveBool($call, $scope, 'patternOrder', 5);
		$lazy = $this->resolveBool($call, $scope, 'lazy', 7);
		if ($captureOffset === null || $unmatchedAsNull === null || $patternOrder === null || $lazy === null) {
			return null;
		}

		$elementType = $this->buildElementType($captureOffset, $unmatchedAsNull);

		if ($lazy) {
			return new GenericObjectType(\Generator::class, [
				new IntegerType,
				new ArrayType(new MixedType, $elementType),
				new MixedType,
				new MixedType,
			]);
		}

		if ($patternOrder) {
			return new ArrayType(
				new MixedType,
				self::buildListType($elementType),
			);
		}

		return self::buildListType(
			new ArrayType(new MixedType, $elementType),
		);
	}


	private function resolveSplit(StaticCall $call, Scope $scope): ?Type
	{
		$captureOffset = $this->resolveBool($call, $scope, 'captureOffset', 2);
		if ($captureOffset === null) {
			return null;
		}

		$elementType = $captureOffset
			? self::buildOffsetTuple(new StringType)
			: new StringType;

		return self::buildListType($elementType);
	}


	private function buildElementType(bool $captureOffset, bool $unmatchedAsNull): Type
	{
		$stringType = $unmatchedAsNull
			? TypeCombinator::addNull(new StringType)
			: new StringType;

		return $captureOffset
			? self::buildOffsetTuple($stringType)
			: $stringType;
	}


	private static function buildOffsetTuple(Type $stringType): Type
	{
		$builder = ConstantArrayTypeBuilder::createEmpty();
		$builder->setOffsetValueType(new ConstantIntegerType(0), $stringType);
		$builder->setOffsetValueType(new ConstantIntegerType(1), IntegerRangeType::fromInterval(0, null));
		return $builder->getArray();
	}


	private static function buildListType(Type $valueType): Type
	{
		return new IntersectionType([
			new ArrayType(IntegerRangeType::createAllGreaterThanOrEqualTo(0), $valueType),
			new AccessoryArrayListType,
		]);
	}


	/**
	 * Resolves a boolean argument by parameter name (named arg) or positional index.
	 * Returns the default (false) when the argument is not provided.
	 */
	private function resolveBool(StaticCall $call, Scope $scope, string $name, int $position): ?bool
	{
		$args = $call->getArgs();

		foreach ($args as $arg) {
			if ($arg->name !== null && $arg->name->toString() === $name) {
				return self::extractBool($scope->getType($arg->value));
			}
		}

		if (isset($args[$position]) && $args[$position]->name === null) {
			return self::extractBool($scope->getType($args[$position]->value));
		}

		return false;
	}


	private static function extractBool(Type $type): ?bool
	{
		if ($type->isTrue()->yes()) {
			return true;
		} elseif ($type->isFalse()->yes()) {
			return false;
		}

		return null;
	}
}

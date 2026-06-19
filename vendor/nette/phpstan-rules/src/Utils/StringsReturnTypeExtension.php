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
 * Narrows return types of Strings::match(), matchAll() and split().
 * For match()/matchAll() with a constant pattern it derives the exact array
 * shape from the regular expression (capture groups) via StringsRegexHelper;
 * otherwise it falls back to a generic shape based on the boolean arguments
 * (captureOffset, unmatchedAsNull, patternOrder, lazy).
 */
class StringsReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function __construct(
		private readonly StringsRegexHelper $helper,
	) {
	}


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
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		return match ($methodReflection->getName()) {
			'match' => $this->resolveMatch($methodCall, $scope),
			'matchAll' => $this->resolveMatchAll($methodCall, $scope),
			'split' => $this->resolveSplit($methodCall, $scope),
			default => null,
		};
	}


	private function resolveMatch(StaticCall $call, Scope $scope): ?Type
	{
		$args = $call->getArgs();
		$captureOffset = StringsRegexHelper::resolveFlag($args, 'captureOffset', 2, $scope);
		$unmatchedAsNull = StringsRegexHelper::resolveFlag($args, 'unmatchedAsNull', 4, $scope);
		if ($captureOffset === null || $unmatchedAsNull === null) {
			return null;
		}

		$patternArg = StringsRegexHelper::findArg($args, 'pattern', 1);
		if ($patternArg !== null) {
			$shape = $this->helper->matchShape($patternArg->value, $captureOffset, $unmatchedAsNull, $scope);
			if ($shape !== null) {
				return TypeCombinator::addNull($shape);
			}
		}

		// fallback: generic shape based on the boolean arguments only
		$elementType = $this->buildElementType($captureOffset, $unmatchedAsNull);
		return TypeCombinator::addNull(
			new ArrayType(new MixedType, $elementType),
		);
	}


	private function resolveMatchAll(StaticCall $call, Scope $scope): ?Type
	{
		$args = $call->getArgs();
		$captureOffset = StringsRegexHelper::resolveFlag($args, 'captureOffset', 2, $scope);
		$unmatchedAsNull = StringsRegexHelper::resolveFlag($args, 'unmatchedAsNull', 4, $scope);
		$patternOrder = StringsRegexHelper::resolveFlag($args, 'patternOrder', 5, $scope);
		$lazy = StringsRegexHelper::resolveFlag($args, 'lazy', 7, $scope);
		if ($captureOffset === null || $unmatchedAsNull === null || $patternOrder === null || $lazy === null) {
			return null;
		}

		$patternArg = StringsRegexHelper::findArg($args, 'pattern', 1);
		if (!$lazy && $patternArg !== null) {
			$shape = $this->helper->matchAllShape($patternArg->value, $captureOffset, $unmatchedAsNull, $patternOrder, $scope);
			if ($shape !== null) {
				return $shape;
			}
		}

		// fallback: generic shape based on the boolean arguments only
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
		$captureOffset = StringsRegexHelper::resolveFlag($call->getArgs(), 'captureOffset', 2, $scope);
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
}

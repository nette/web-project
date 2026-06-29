<?php declare(strict_types=1);

namespace Nette\PHPStan\Tester;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use Tester\Assert;
use function count;


/**
 * Narrows variable types after Tester\Assert assertion calls.
 */
class AssertTypeNarrowingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
	private TypeSpecifier $typeSpecifier;


	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}


	public function getClass(): string
	{
		return Assert::class;
	}


	public function isStaticMethodSupported(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		TypeSpecifierContext $context,
	): bool
	{
		$minArgs = match ($staticMethodReflection->getName()) {
			'null', 'notNull', 'true', 'false', 'truthy', 'falsey' => 1,
			'same', 'notSame', 'type' => 2,
			default => null,
		};
		return $minArgs !== null && count($node->getArgs()) >= $minArgs;
	}


	public function specifyTypes(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		Scope $scope,
		TypeSpecifierContext $context,
	): SpecifiedTypes
	{
		$args = $node->getArgs();
		$expression = match ($staticMethodReflection->getName()) {
			'null' => new Identical($args[0]->value, new ConstFetch(new Name('null'))),
			'notNull' => new NotIdentical($args[0]->value, new ConstFetch(new Name('null'))),
			'true' => new Identical($args[0]->value, new ConstFetch(new Name('true'))),
			'false' => new Identical($args[0]->value, new ConstFetch(new Name('false'))),
			'truthy' => $args[0]->value,
			'falsey' => new BooleanNot($args[0]->value),
			'same' => new Identical($args[1]->value, $args[0]->value),
			'notSame' => new NotIdentical($args[1]->value, $args[0]->value),
			'type' => $this->createTypeExpression($scope, $args),
			default => null,
		};

		if ($expression === null) {
			return new SpecifiedTypes([], []);
		}

		return $this->typeSpecifier->specifyTypesInCondition(
			$scope,
			$expression,
			TypeSpecifierContext::createTruthy(),
		)->setRootExpr($expression);
	}


	/**
	 * @param  Arg[]  $args
	 */
	private function createTypeExpression(Scope $scope, array $args): ?Expr
	{
		$typeType = $scope->getType($args[0]->value);
		$constantStrings = $typeType->getConstantStrings();

		if (count($constantStrings) === 1) {
			$typeName = $constantStrings[0]->getValue();

			$func = match ($typeName) {
				'list', 'array' => 'is_array',
				'bool' => 'is_bool',
				'callable' => 'is_callable',
				'float' => 'is_float',
				'int', 'integer' => 'is_int',
				'null' => 'is_null',
				'object' => 'is_object',
				'resource' => 'is_resource',
				'scalar' => 'is_scalar',
				'string' => 'is_string',
				default => null,
			};

			return $func !== null
				? new FuncCall(new Name($func), [$args[1]])
				: new Instanceof_($args[1]->value, new Name($typeName));
		}

		// object argument → instanceof using its class
		$classNames = $typeType->getObjectClassNames();
		if (count($classNames) === 1) {
			return new Instanceof_($args[1]->value, new Name($classNames[0]));
		}

		return null;
	}
}

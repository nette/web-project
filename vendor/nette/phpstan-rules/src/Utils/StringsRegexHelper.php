<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Php\RegexArrayShapeMatcher;
use PHPStan\Type\Type;


/**
 * Shared helper for the Nette\Utils\Strings regex extensions. Centralizes the
 * translation of Nette's boolean parameters into a PREG flag mask, the calls to
 * phpstan's RegexArrayShapeMatcher, and the resolution of call arguments.
 */
final class StringsRegexHelper
{
	public function __construct(
		private readonly RegexArrayShapeMatcher $matcher,
	) {
	}


	/**
	 * Exact array shape of a single Strings::match() result on a successful match
	 * (group 0 always present; the caller adds null for the no-match case).
	 */
	public function matchShape(Expr $patternExpr, bool $captureOffset, bool $unmatchedAsNull, Scope $scope): ?Type
	{
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0)
			| ($unmatchedAsNull ? PREG_UNMATCHED_AS_NULL : 0);

		return $this->matcher->matchExpr(
			$patternExpr,
			new ConstantIntegerType($flags),
			TrinaryLogic::createYes(),
			$scope,
		);
	}


	/**
	 * Exact array shape of a Strings::matchAll() result. Mirrors Nette's default
	 * of PREG_SET_ORDER (PREG_PATTERN_ORDER only when $patternOrder is true).
	 */
	public function matchAllShape(
		Expr $patternExpr,
		bool $captureOffset,
		bool $unmatchedAsNull,
		bool $patternOrder,
		Scope $scope,
	): ?Type
	{
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0)
			| ($unmatchedAsNull ? PREG_UNMATCHED_AS_NULL : 0)
			| ($patternOrder ? PREG_PATTERN_ORDER : PREG_SET_ORDER);

		return $this->matcher->matchAllExpr(
			$patternExpr,
			new ConstantIntegerType($flags),
			TrinaryLogic::createYes(),
			$scope,
		);
	}


	/**
	 * Resolves a boolean argument by name (named arg) or positional index.
	 * Returns the default (false) when not provided, or null when not a constant bool.
	 * Stateless utility (does not need the matcher), hence static.
	 * @param array<int, Arg> $args
	 */
	public static function resolveFlag(array $args, string $name, int $position, Scope $scope): ?bool
	{
		$arg = self::findArg($args, $name, $position);
		if ($arg === null) {
			return false;
		}

		$type = $scope->getType($arg->value);
		if ($type->isTrue()->yes()) {
			return true;
		} elseif ($type->isFalse()->yes()) {
			return false;
		}

		return null;
	}


	/**
	 * Finds an argument by name (named arg) or positional index.
	 * Stateless utility (does not need the matcher), hence static.
	 * @param array<int, Arg> $args
	 */
	public static function findArg(array $args, string $name, int $position): ?Arg
	{
		foreach ($args as $arg) {
			if ($arg->name !== null && $arg->name->toString() === $name) {
				return $arg;
			}
		}

		return isset($args[$position]) && $args[$position]->name === null ? $args[$position] : null;
	}
}

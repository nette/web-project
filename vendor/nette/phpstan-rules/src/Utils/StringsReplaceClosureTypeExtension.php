<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\Strings;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\ClosureType;
use PHPStan\Type\StaticMethodParameterClosureTypeExtension;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;


/**
 * Infers the parameter type of the $replacement callback passed to
 * Strings::replace() from the (constant) regular expression pattern, so the
 * callback's $matches argument has the exact array shape of its capture groups.
 */
final class StringsReplaceClosureTypeExtension implements StaticMethodParameterClosureTypeExtension
{
	public function __construct(
		private readonly StringsRegexHelper $helper,
	) {
	}


	public function isStaticMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
	{
		return $methodReflection->getDeclaringClass()->getName() === Strings::class
			&& $methodReflection->getName() === 'replace'
			&& $parameter->getName() === 'replacement';
	}


	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		ParameterReflection $parameter,
		Scope $scope,
	): ?Type
	{
		$args = $methodCall->getArgs();
		$patternArg = StringsRegexHelper::findArg($args, 'pattern', 1);
		if ($patternArg === null) {
			return null;
		}

		$captureOffset = StringsRegexHelper::resolveFlag($args, 'captureOffset', 4, $scope);
		$unmatchedAsNull = StringsRegexHelper::resolveFlag($args, 'unmatchedAsNull', 5, $scope);
		if ($captureOffset === null || $unmatchedAsNull === null) {
			return null;
		}

		$matchesType = $this->helper->matchShape($patternArg->value, $captureOffset, $unmatchedAsNull, $scope);
		if ($matchesType === null) {
			return null;
		}

		return new ClosureType(
			[new NativeParameterReflection('matches', false, $matchesType, PassedByReference::createNo(), false, null)],
			new StringType,
		);
	}
}

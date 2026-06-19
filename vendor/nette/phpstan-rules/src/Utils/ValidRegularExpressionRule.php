<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\RegexpException;
use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use function in_array, sprintf, strtolower;


/**
 * Reports invalid regular expression patterns passed to Nette\Utils\Strings
 * methods (match, matchAll, split, replace). Only constant string patterns are
 * checked; for replace() the array keys (pattern => replacement) are checked too.
 *
 * @implements Rule<StaticCall>
 */
final class ValidRegularExpressionRule implements Rule
{
	public function getNodeType(): string
	{
		return StaticCall::class;
	}


	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		foreach ($this->extractPatterns($node, $scope) as $pattern) {
			$error = $this->validatePattern($pattern);
			if ($error !== null) {
				$errors[] = RuleErrorBuilder::message(sprintf('Invalid regular expression pattern: %s', $error))
					->identifier('nette.strings.regexpPattern')
					->build();
			}
		}

		return $errors;
	}


	/**
	 * @return list<string>
	 */
	private function extractPatterns(StaticCall $call, Scope $scope): array
	{
		if (!$call->class instanceof Node\Name || !$call->name instanceof Node\Identifier) {
			return [];
		}

		$callerType = $scope->resolveTypeByName($call->class);
		if (!(new ObjectType(Strings::class))->isSuperTypeOf($callerType)->yes()) {
			return [];
		}

		$methodName = strtolower($call->name->toString());
		if (!in_array($methodName, ['match', 'matchall', 'split', 'replace'], true)) {
			return [];
		}

		// rules receive raw (non-normalized) args, so resolve the pattern argument
		// by its name (handles reordered named args) and fall back to position 1
		$patternArg = StringsRegexHelper::findArg($call->getArgs(), 'pattern', 1);
		if ($patternArg === null) {
			return [];
		}

		$patternType = $scope->getType($patternArg->value);
		$patterns = [];
		foreach ($patternType->getConstantStrings() as $constantString) {
			$patterns[] = $constantString->getValue();
		}

		if ($methodName === 'replace') {
			foreach ($patternType->getConstantArrays() as $constantArray) {
				foreach ($constantArray->getKeyTypes() as $keyType) {
					foreach ($keyType->getConstantStrings() as $constantString) {
						$patterns[] = $constantString->getValue();
					}
				}
			}
		}

		return $patterns;
	}


	private function validatePattern(string $pattern): ?string
	{
		try {
			Strings::match('', $pattern);
			return null;
		} catch (RegexpException $e) {
			// code 0 = pattern compilation error (invalid syntax); a non-zero code
			// is a runtime PCRE error (backtrack/recursion limit) unrelated to syntax
			return $e->getCode() === 0 ? $e->getMessage() : null;
		}
	}
}

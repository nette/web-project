<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\Strings;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Php\RegexArrayShapeMatcher;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use function count, in_array;


/**
 * Narrows the subject string type after a successful Strings::match()/matchAll()
 * call — e.g. inside `if (Strings::match($s, '#...#'))` the subject $s may be
 * narrowed (to non-empty-string etc.) when the pattern guarantees it.
 */
class StringsMatchTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
	private TypeSpecifier $typeSpecifier;


	public function __construct(
		private readonly RegexArrayShapeMatcher $matcher,
	) {
	}


	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}


	public function getClass(): string
	{
		return Strings::class;
	}


	public function isStaticMethodSupported(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		TypeSpecifierContext $context,
	): bool
	{
		return $context->true()
			&& in_array($staticMethodReflection->getName(), ['match', 'matchAll'], true)
			&& count($node->getArgs()) >= 2;
	}


	public function specifyTypes(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		Scope $scope,
		TypeSpecifierContext $context,
	): SpecifiedTypes
	{
		$args = $node->getArgs();
		$subjectArg = $args[0];
		$patternArg = $args[1];

		if (!$scope->getType($subjectArg->value)->isString()->yes()) {
			return new SpecifiedTypes;
		}

		$subjectType = $this->matcher->matchSubjectExpr($patternArg->value, $scope);
		if ($subjectType === null) {
			return new SpecifiedTypes;
		}

		return $this->typeSpecifier
			->create($subjectArg->value, $subjectType, $context, $scope)
			->setRootExpr($node);
	}
}

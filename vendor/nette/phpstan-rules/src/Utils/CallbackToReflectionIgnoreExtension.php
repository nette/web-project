<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use Nette\Utils\Callback;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use function strtolower;


/**
 * Suppresses 'argument.type' on the argument of Nette\Utils\Callback::toReflection().
 * Its @param callable is intentionally strict, but the method escalates the type check to
 * ReflectionException at runtime, so passing a value that cannot be statically proven callable
 * (typically a [class-string, method-name] tuple) is valid by design.
 */
class CallbackToReflectionIgnoreExtension implements IgnoreErrorExtension
{
	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if (
			$error->getIdentifier() !== 'argument.type'
			|| !$node instanceof StaticCall
			|| !$node->class instanceof Name
			|| !$node->name instanceof Identifier
			|| strtolower($node->name->name) !== 'toreflection'
		) {
			return false;
		}

		return (new ObjectType(Callback::class))
			->isSuperTypeOf($scope->resolveTypeByName($node->class))
			->yes();
	}
}

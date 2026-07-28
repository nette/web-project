<?php declare(strict_types=1);

namespace Nette\PHPStan\Php;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;


/**
 * Suppresses property.notFound for property reads that are declared by a @property-read
 * tag on an interface. Writes are intentionally not suppressed (the tags are read-only
 * contracts and write support would need assign type-checking).
 */
final class InterfacePropertyTagIgnoreExtension implements IgnoreErrorExtension
{
	public function __construct(
		private readonly InterfacePropertyTagResolver $resolver,
	) {
	}


	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if (
			$error->getIdentifier() !== 'property.notFound'
			|| (!$node instanceof Expr\PropertyFetch && !$node instanceof Expr\NullsafePropertyFetch)
			|| !$node->name instanceof Identifier
		) {
			return false;
		}

		return $this->resolver->resolve($scope->getType($node->var), $node->name->toString()) !== null;
	}
}

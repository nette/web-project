<?php declare(strict_types=1);

namespace Nette\PHPStan\Php;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Type;


/**
 * Gives property reads on interfaces the type declared by their @property-read tag.
 * Nullsafe reads are covered too: PHPStan resolves them through a synthetic PropertyFetch.
 */
final class InterfacePropertyTagTypeExtension implements ExpressionTypeResolverExtension
{
	public function __construct(
		private readonly InterfacePropertyTagResolver $resolver,
	) {
	}


	public function getType(Expr $expr, Scope $scope): ?Type
	{
		if (
			!$expr instanceof Expr\PropertyFetch
			|| !$expr->name instanceof Identifier
			|| $scope->hasExpressionType($expr)->yes() // a narrowed type must win
		) {
			return null;
		}

		return $this->resolver->resolve($scope->getType($expr->var), $expr->name->toString());
	}
}

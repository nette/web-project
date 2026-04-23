<?php declare(strict_types=1);

namespace Nette\PHPStan\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Node\NoopExpressionNode;


/**
 * Suppresses 'expr.resultUnused' for the runtime type validation pattern (function(Type ...$p) {})(...$args).
 */
class ClosureTypeCheckIgnoreExtension implements IgnoreErrorExtension
{
	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if ($error->getIdentifier() !== 'expr.resultUnused') {
			return false;
		}

		if (!$node instanceof NoopExpressionNode) {
			return false;
		}

		$expr = $node->getOriginalExpr();
		if (!$expr instanceof FuncCall || !$expr->name instanceof Closure) {
			return false;
		}

		$closure = $expr->name;
		if ($closure->stmts !== [] || $closure->params === []) {
			return false;
		}

		foreach ($closure->params as $param) {
			if (!$param->variadic || $param->type === null) {
				return false;
			}
		}

		return true;
	}
}

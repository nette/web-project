<?php declare(strict_types=1);

namespace Nette\PHPStan\Application;

use Nette\Application\AbortException;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function array_map, implode, is_array, sprintf;


/**
 * Reports a try/catch where the try block can throw Nette\Application\AbortException
 * (e.g. via redirect(), forward(), terminate(), sendJson()) but the first catch that
 * would catch it — typically a broad catch (\Throwable) or catch (\Exception) — swallows
 * it instead of rethrowing. Swallowing AbortException silently breaks redirects.
 *
 * @implements Rule<TryCatch>
 */
final class RethrowAbortExceptionRule implements Rule
{
	private const AbortException = AbortException::class;


	public function getNodeType(): string
	{
		return TryCatch::class;
	}


	public function processNode(Node $node, Scope $scope): array
	{
		$abortType = new ObjectType(self::AbortException);

		// only relevant when the try block can actually throw AbortException
		if (!$this->throwsAbort($node->stmts, $scope, $abortType)) {
			return [];
		}

		foreach ($node->catches as $catch) {
			$caughtType = TypeCombinator::union(
				...array_map(static fn(Name $name): ObjectType => new ObjectType($name->toString()), $catch->types),
			);

			// PHP picks the first catch that matches; that one decides AbortException's fate
			if (!$caughtType->isSuperTypeOf($abortType)->yes()) {
				continue;
			}

			if ($this->containsThrow($catch->stmts)) {
				return [];
			}

			$caught = implode('|', array_map(static fn(Name $name): string => $name->toString(), $catch->types));
			return [
				RuleErrorBuilder::message(sprintf(
					'Catch block for %s swallows Nette\Application\AbortException thrown in the try block, which silently breaks redirect()/forward()/terminate(). Rethrow it, or add a separate catch (\Nette\Application\AbortException) branch before this one.',
					$caught,
				))
					->identifier('nette.abortException')
					->build(),
			];
		}

		return [];
	}


	/**
	 * @param Node[] $stmts
	 */
	private function throwsAbort(array $stmts, Scope $scope, Type $abortType): bool
	{
		$found = false;
		$this->walk($stmts, function (Node $node) use ($scope, $abortType, &$found): void {
			if ($found) {
				return;
			}

			// Only count calls whose throw type IS AbortException (or a subtype).
			// We intentionally do not match wider throw types such as \Throwable or
			// \Exception (supertypes of AbortException): a method merely declaring
			// @throws \Throwable almost never actually throws AbortException, and
			// matching it would flag every generic try/catch (false positives).
			$throwType = $this->callThrowType($node, $scope);
			if ($throwType !== null && $abortType->isSuperTypeOf($throwType)->yes()) {
				$found = true;
			}
		});

		return $found;
	}


	private function callThrowType(Node $node, Scope $scope): ?Type
	{
		if ($node instanceof MethodCall && $node->name instanceof Node\Identifier) {
			$calledOn = $scope->getType($node->var);
			$name = $node->name->toString();
			return $calledOn->hasMethod($name)->yes()
				? $calledOn->getMethod($name, $scope)->getThrowType()
				: null;
		}

		if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Node\Identifier) {
			$calledOn = $scope->resolveTypeByName($node->class);
			$name = $node->name->toString();
			return $calledOn->hasMethod($name)->yes()
				? $calledOn->getMethod($name, $scope)->getThrowType()
				: null;
		}

		return null;
	}


	/**
	 * @param Node[] $stmts
	 */
	private function containsThrow(array $stmts): bool
	{
		$found = false;
		$this->walk($stmts, function (Node $node) use (&$found): void {
			if ($node instanceof Throw_) {
				$found = true;
			}
		});

		return $found;
	}


	/**
	 * Recursively visits every node in the given statements. Does not descend into
	 * nested closures/functions — their bodies do not run as part of the try/catch
	 * control flow, so a redirect() or throw inside them must not count here.
	 * @param mixed $nodes
	 * @param callable(Node): void $callback
	 */
	private function walk($nodes, callable $callback): void
	{
		if ($nodes instanceof Node) {
			$callback($nodes);
			if ($nodes instanceof FunctionLike) {
				return;
			}
			foreach ($nodes->getSubNodeNames() as $name) {
				$this->walk($nodes->{$name}, $callback);
			}
		} elseif (is_array($nodes)) {
			foreach ($nodes as $child) {
				$this->walk($child, $callback);
			}
		}
	}
}

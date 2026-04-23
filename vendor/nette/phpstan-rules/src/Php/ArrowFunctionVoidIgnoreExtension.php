<?php declare(strict_types=1);

namespace Nette\PHPStan\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use function explode, preg_match, str_contains, strtolower;


/**
 * Suppresses 'argument.type' when an arrow function (which always returns a value) is passed
 * to a parameter typed as Closure(): void. The list of affected functions/methods is configurable.
 */
class ArrowFunctionVoidIgnoreExtension implements IgnoreErrorExtension
{
	/** @var array<string, true> */
	private array $functions = [];

	/** @var array<lowercase-string, array<lowercase-string, true>> */
	private array $methods = [];


	/**
	 * @param list<string> $items  plain names for functions, Class::method for methods
	 */
	public function __construct(
		array $items,
		private ReflectionProvider $reflectionProvider,
	) {
		foreach ($items as $item) {
			if (str_contains($item, '::')) {
				[$class, $method] = explode('::', $item, 2);
				$this->methods[strtolower($class)][strtolower($method)] = true;
			} else {
				$this->functions[$item] = true;
			}
		}
	}


	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if ($error->getIdentifier() !== 'argument.type') {
			return false;
		}

		$message = $error->getMessage();
		if (!preg_match('~(?:Closure|callable)\(.*\): void.*, Closure\(~', $message)) {
			return false;
		}

		if ($node instanceof FuncCall && $node->name instanceof Name) {
			$name = $this->reflectionProvider->resolveFunctionName($node->name, $scope);
			return $name !== null && isset($this->functions[$name]);
		}

		if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier) {
			$className = $scope->resolveName($node->class);
			$methodName = $node->name->name;
			return isset($this->methods[strtolower($className)][strtolower($methodName)]);
		}

		if ($node instanceof MethodCall && $node->name instanceof Identifier) {
			$methodName = $node->name->name;
			foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
				if (isset($this->methods[strtolower($className)][strtolower($methodName)])) {
					return true;
				}
			}
		}

		return false;
	}
}

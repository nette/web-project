<?php declare(strict_types=1);

namespace Nette\PHPStan\Components;

use Nette\Forms\Container;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use ReflectionNamedType;
use function array_pop, is_array, is_string, str_starts_with, ucfirst;


/**
 * Resolves the type of a named child component within a Nette component tree.
 *
 * Two resolution strategies are combined:
 *  - Forms: scan addXxx('name') calls in the factory/method body that builds the container
 *  - Component Model: look up the createComponent<Name>() factory on the container's class
 *
 * Supports single-level access, chained access ($form['a']['b']) and the dash
 * notation ($this['a-b']) that Nette expands to nested getComponent() calls.
 */
final class ComponentTreeResolver
{
	public function __construct(
		private readonly Parser $parser,
		private readonly ReflectionProvider $reflectionProvider,
	) {
	}


	/**
	 * Single-level access where the container is a local variable: $form['name'].
	 * Traces addXxx() in the current body, following assignments back to source factories.
	 */
	public function resolveFromLocalVar(Scope $scope, string $variableName, string $componentName): ?Type
	{
		$stmts = $this->parser->parseFile($scope->getFile());
		$body = $this->findEnclosingBody($stmts, $scope);
		if ($body === null) {
			return null;
		}

		$className = $scope->isInClass() ? $scope->getClassReflection()->getName() : null;
		return $this->resolveAddMethodType(
			$this->findAddCallRecursive($body, $variableName, $componentName, $className),
		);
	}


	/**
	 * Chained access: $prefixExpr['child']. Resolves the prefix expression to its
	 * source factory and then the child within it.
	 */
	public function resolveChainedChild(Expr $prefixExpr, string $childName, Scope $scope): ?Type
	{
		$className = $scope->isInClass() ? $scope->getClassReflection()->getName() : null;
		$factory = $className !== null ? $this->resolveExprToSourceMethod($prefixExpr, $className) : null;
		$type = $factory !== null
			? $factory->getVariants()[0]->getReturnType()
			: $scope->getType($prefixExpr);

		return $this->resolveChildOf($type, $factory, $childName, $scope);
	}


	/**
	 * Dash notation: $caller['seg1-seg2-...-child']. The prefix segments are resolved
	 * via createComponent factories (reflection); the last segment within the reached container.
	 * @param  string[]  $segments
	 */
	public function resolveDashPath(Expr $caller, array $segments, Scope $scope): ?Type
	{
		$type = $scope->getType($caller);
		$factory = null;

		$prefix = $segments;
		$last = array_pop($prefix);
		if ($last === null) {
			return null;
		}

		foreach ($prefix as $segment) {
			$factoryName = 'createComponent' . ucfirst($segment);
			if (!$type->hasMethod($factoryName)->yes()) {
				return null; // intermediate is not a createComponent factory (e.g. form sub-container) → out of scope
			}

			$factory = $type->getMethod($factoryName, $scope);
			$type = $factory->getVariants()[0]->getReturnType();
		}

		return $this->resolveChildOf($type, $factory, $last, $scope);
	}


	/**
	 * Resolves a child component within a container, given its static type and
	 * optionally the factory method whose body constructs it.
	 */
	public function resolveChildOf(Type $containerType, ?MethodReflection $factory, string $name, Scope $scope): ?Type
	{
		// 1) addXxx() in the factory body - only when the container can be a form,
		// otherwise an add*() call on a non-form control could be mistaken for a form control
		$formContainer = new ObjectType(Container::class);
		if ($factory !== null && !$formContainer->isSuperTypeOf($containerType)->no()) {
			$body = $this->getMethodBody($factory);
			$returnedVar = $body !== null ? $this->findReturnedVariableName($body) : null;
			if ($body !== null && $returnedVar !== null) {
				$type = $this->resolveAddMethodType(
					$this->findAddCallRecursive($body, $returnedVar, $name, $factory->getDeclaringClass()->getName()),
				);
				if ($type !== null) {
					return $type;
				}
			}
		}

		// 2) createComponent<Name>() factory on the container's class
		$factoryName = 'createComponent' . ucfirst($name);
		if ($containerType->hasMethod($factoryName)->yes()) {
			return $containerType->getMethod($factoryName, $scope)->getVariants()[0]->getReturnType();
		}

		return null;
	}


	/**
	 * Searches for addXxx('componentName') in the given body, then recursively
	 * follows variable assignments to source factory methods.
	 * @param  Stmt[]  $stmts
	 */
	private function findAddCallRecursive(
		array $stmts,
		string $variableName,
		string $componentName,
		?string $contextClassName,
		int $depth = 3,
	): ?string
	{
		$result = $this->findAddCall($stmts, $variableName, $componentName);
		if ($result !== null) {
			return $result;
		}

		if ($depth <= 0 || $contextClassName === null) {
			return null;
		}

		$sourceMethod = $this->traceToSourceMethod($stmts, $variableName, $contextClassName);
		if ($sourceMethod === null) {
			return null;
		}

		$sourceBody = $this->getMethodBody($sourceMethod);
		if ($sourceBody === null) {
			return null;
		}

		$returnedVar = $this->findReturnedVariableName($sourceBody);
		if ($returnedVar === null) {
			return null;
		}

		return $this->findAddCallRecursive(
			$sourceBody,
			$returnedVar,
			$componentName,
			$sourceMethod->getDeclaringClass()->getName(),
			$depth - 1,
		);
	}


	/**
	 * Finds the method that produces the value assigned to $variableName.
	 * @param  Stmt[]  $stmts
	 */
	private function traceToSourceMethod(
		array $stmts,
		string $variableName,
		string $contextClassName,
	): ?MethodReflection
	{
		$assignExpr = $this->findVariableAssignment($stmts, $variableName);
		if ($assignExpr === null) {
			return null;
		}

		return $this->resolveExprToSourceMethod($assignExpr, $contextClassName);
	}


	/**
	 * Resolves an expression that denotes a container to the method whose body builds it:
	 * $this['xxx'] / $this->getComponent('xxx') → createComponentXxx(); $obj->method() → that method.
	 */
	private function resolveExprToSourceMethod(Expr $expr, string $contextClassName): ?MethodReflection
	{
		// $this['xxx'] → createComponentXxx()
		if (
			$expr instanceof ArrayDimFetch
			&& $expr->var instanceof Variable
			&& $expr->var->name === 'this'
			&& $expr->dim instanceof String_
		) {
			$factoryName = 'createComponent' . ucfirst($expr->dim->value);
			return $this->getMethodIfExists($contextClassName, $factoryName);
		}

		// $this->getComponent('xxx') → createComponentXxx()
		if (
			$expr instanceof MethodCall
			&& $expr->var instanceof Variable
			&& $expr->var->name === 'this'
			&& $expr->name instanceof Identifier
			&& $expr->name->toString() === 'getComponent'
			&& $expr->getArgs() !== []
		) {
			$nameArg = $expr->getArgs()[0]->value;
			if ($nameArg instanceof String_) {
				$factoryName = 'createComponent' . ucfirst($nameArg->value);
				return $this->getMethodIfExists($contextClassName, $factoryName);
			}
		}

		// $obj->method() → resolve class, get method
		if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
			$callerClass = $this->resolveExprClassName($expr->var, $contextClassName);
			if ($callerClass !== null) {
				return $this->getMethodIfExists($callerClass, $expr->name->toString());
			}
		}

		return null;
	}


	/**
	 * @return Stmt[]|null
	 */
	private function getMethodBody(MethodReflection $method): ?array
	{
		$declaringClass = $method->getDeclaringClass();
		$fileName = $declaringClass->getNativeReflection()->getFileName();
		if ($fileName === false) {
			return null;
		}

		$stmts = $this->parser->parseFile($fileName);
		return $this->searchBody($stmts, $method->getName(), $declaringClass->getName());
	}


	/**
	 * Finds the expression assigned to $variableName.
	 * @param  Stmt[]  $stmts
	 */
	private function findVariableAssignment(array $stmts, string $variableName): ?Expr
	{
		foreach ($stmts as $stmt) {
			$result = $this->walkForAssignment($stmt, $variableName);
			if ($result !== null) {
				return $result;
			}
		}

		return null;
	}


	private function walkForAssignment(Node $node, string $variableName): ?Expr
	{
		if (
			$node instanceof Assign
			&& $node->var instanceof Variable
			&& $node->var->name === $variableName
		) {
			return $node->expr;
		}

		foreach ($node->getSubNodeNames() as $name) {
			$subNode = $node->$name;
			if ($subNode instanceof Node) {
				$result = $this->walkForAssignment($subNode, $variableName);
				if ($result !== null) {
					return $result;
				}
			} elseif (is_array($subNode)) {
				foreach ($subNode as $item) {
					if ($item instanceof Node) {
						$result = $this->walkForAssignment($item, $variableName);
						if ($result !== null) {
							return $result;
						}
					}
				}
			}
		}

		return null;
	}


	/**
	 * Finds the variable name from a return statement.
	 * @param  Stmt[]  $stmts
	 */
	private function findReturnedVariableName(array $stmts): ?string
	{
		foreach ($stmts as $stmt) {
			$result = $this->walkForReturn($stmt);
			if ($result !== null) {
				return $result;
			}
		}

		return null;
	}


	private function walkForReturn(Node $node): ?string
	{
		if (
			$node instanceof Stmt\Return_
			&& $node->expr instanceof Variable
			&& is_string($node->expr->name)
		) {
			return $node->expr->name;
		}

		// Don't descend into closures or anonymous classes
		if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction || $node instanceof Stmt\Class_) {
			return null;
		}

		foreach ($node->getSubNodeNames() as $name) {
			$subNode = $node->$name;
			if ($subNode instanceof Node) {
				$result = $this->walkForReturn($subNode);
				if ($result !== null) {
					return $result;
				}
			} elseif (is_array($subNode)) {
				foreach ($subNode as $item) {
					if ($item instanceof Node) {
						$result = $this->walkForReturn($item);
						if ($result !== null) {
							return $result;
						}
					}
				}
			}
		}

		return null;
	}


	private function resolveExprClassName(Expr $expr, string $contextClassName): ?string
	{
		if ($expr instanceof Variable && $expr->name === 'this') {
			return $contextClassName;
		}

		if (
			$expr instanceof PropertyFetch
			&& $expr->var instanceof Variable
			&& $expr->var->name === 'this'
			&& $expr->name instanceof Identifier
		) {
			return $this->resolvePropertyClassName($contextClassName, $expr->name->toString());
		}

		return null;
	}


	private function resolvePropertyClassName(string $className, string $propertyName): ?string
	{
		if (!$this->reflectionProvider->hasClass($className)) {
			return null;
		}

		$nativeRefl = $this->reflectionProvider->getClass($className)->getNativeReflection();
		if (!$nativeRefl->hasProperty($propertyName)) {
			return null;
		}

		$type = $nativeRefl->getProperty($propertyName)->getType();
		if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
			return $type->getName();
		}

		return null;
	}


	private function getMethodIfExists(string $className, string $methodName): ?MethodReflection
	{
		if (!$this->reflectionProvider->hasClass($className)) {
			return null;
		}

		$classRefl = $this->reflectionProvider->getClass($className);
		if (!$classRefl->hasMethod($methodName)) {
			return null;
		}

		return $classRefl->getNativeMethod($methodName);
	}


	private function resolveAddMethodType(?string $addMethodName): ?Type
	{
		if ($addMethodName === null) {
			return null;
		}

		$containerClass = $this->reflectionProvider->getClass(Container::class);
		if (!$containerClass->hasMethod($addMethodName)) {
			return null;
		}

		return $containerClass->getNativeMethod($addMethodName)->getVariants()[0]->getReturnType();
	}


	/**
	 * @param  Stmt[]  $stmts
	 * @return Stmt[]|null
	 */
	private function findEnclosingBody(array $stmts, Scope $scope): ?array
	{
		$function = $scope->getFunction();
		if ($function === null) {
			return $stmts;
		}

		$functionName = $function->getName();
		$className = $scope->isInClass() ? $scope->getClassReflection()->getName() : null;
		return $this->searchBody($stmts, $functionName, $className);
	}


	/**
	 * @param  Stmt[]  $stmts
	 * @return Stmt[]|null
	 */
	private function searchBody(array $stmts, string $functionName, ?string $className): ?array
	{
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Stmt\Namespace_) {
				$result = $this->searchBody($stmt->stmts, $functionName, $className);
				if ($result !== null) {
					return $result;
				}

			} elseif (
				$className !== null
				&& ($stmt instanceof Stmt\Class_ || $stmt instanceof Stmt\Trait_)
				&& $stmt->namespacedName !== null
				&& $stmt->namespacedName->toString() === $className
			) {
				foreach ($stmt->stmts as $member) {
					if ($member instanceof Stmt\ClassMethod && $member->name->toString() === $functionName) {
						return $member->stmts ?? [];
					}
				}
			} elseif (
				$className === null
				&& $stmt instanceof Stmt\Function_
				&& $stmt->namespacedName !== null
				&& $stmt->namespacedName->toString() === $functionName
			) {
				return $stmt->stmts ?? [];
			}
		}

		return null;
	}


	/**
	 * Walks AST to find $variable->addXxx('componentName', ...) call.
	 * @param  Stmt[]  $stmts
	 */
	private function findAddCall(array $stmts, string $variableName, string $componentName): ?string
	{
		foreach ($stmts as $stmt) {
			$result = $this->walkNode($stmt, $variableName, $componentName);
			if ($result !== null) {
				return $result;
			}
		}

		return null;
	}


	private function walkNode(Node $node, string $variableName, string $componentName): ?string
	{
		if (
			$node instanceof MethodCall
			&& $node->var instanceof Variable
			&& $node->var->name === $variableName
			&& $node->name instanceof Identifier
			&& str_starts_with($node->name->toString(), 'add')
			&& $node->getArgs() !== []
		) {
			$firstArg = $node->getArgs()[0]->value;
			if ($firstArg instanceof String_ && $firstArg->value === $componentName) {
				return $node->name->toString();
			}
		}

		foreach ($node->getSubNodeNames() as $name) {
			$subNode = $node->$name;
			if ($subNode instanceof Node) {
				$result = $this->walkNode($subNode, $variableName, $componentName);
				if ($result !== null) {
					return $result;
				}
			} elseif (is_array($subNode)) {
				foreach ($subNode as $item) {
					if ($item instanceof Node) {
						$result = $this->walkNode($item, $variableName, $componentName);
						if ($result !== null) {
							return $result;
						}
					}
				}
			}
		}

		return null;
	}
}

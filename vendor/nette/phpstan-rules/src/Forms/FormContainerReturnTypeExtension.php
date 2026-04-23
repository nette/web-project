<?php declare(strict_types=1);

namespace Nette\PHPStan\Forms;

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
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionNamedType;
use function count, in_array, is_array, is_string, str_starts_with, ucfirst;


/**
 * Narrows return types of Forms\Container::getComponent() and ::offsetGet()
 * by finding the corresponding addXxx() call in the same function body
 * or by tracing variable assignments back through factory methods.
 */
class FormContainerReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private Parser $parser,
		private ReflectionProvider $reflectionProvider,
	) {
	}


	public function getClass(): string
	{
		return 'Nette\Forms\Container';
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['getComponent', 'offsetGet'], true);
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		$nameType = $scope->getType($args[0]->value);
		$constantStrings = $nameType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		$componentName = $constantStrings[0]->getValue();
		$type = $this->resolveComponentType($methodCall, $scope, $componentName);

		// Respect $throw parameter for getComponent()
		if ($methodReflection->getName() === 'getComponent' && count($args) >= 2) {
			$throwType = $scope->getType($args[1]->value);
			if (!$throwType->isTrue()->yes()) {
				$type = TypeCombinator::addNull($type);
			}
		}

		return $type;
	}


	private function resolveComponentType(MethodCall $methodCall, Scope $scope, string $componentName): Type
	{
		// Try to find addXxx() call in the same function body or via factory methods
		if ($methodCall->var instanceof Variable && is_string($methodCall->var->name)) {
			$type = $this->resolveFromAddCall($scope, $methodCall->var->name, $componentName);
			if ($type !== null) {
				return $type;
			}
		}

		// Fallback: try createComponent*() factory method
		$factoryMethodName = 'createComponent' . ucfirst($componentName);
		$callerType = $scope->getType($methodCall->var);
		if ($callerType->hasMethod($factoryMethodName)->yes()) {
			$factoryMethod = $callerType->getMethod($factoryMethodName, $scope);
			return $factoryMethod->getVariants()[0]->getReturnType();
		}

		// Fallback: when we can't determine the specific control type,
		// return BaseControl (most $form['field'] accesses are controls, not containers)
		return new ObjectType('Nette\Forms\Controls\BaseControl');
	}


	private function resolveFromAddCall(Scope $scope, string $variableName, string $componentName): ?Type
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

		// $var = $this['xxx'] → createComponentXxx()
		if (
			$assignExpr instanceof ArrayDimFetch
			&& $assignExpr->var instanceof Variable
			&& $assignExpr->var->name === 'this'
			&& $assignExpr->dim instanceof String_
		) {
			$factoryName = 'createComponent' . ucfirst($assignExpr->dim->value);
			return $this->getMethodIfExists($contextClassName, $factoryName);
		}

		// $var = $this->getComponent('xxx') → createComponentXxx()
		if (
			$assignExpr instanceof MethodCall
			&& $assignExpr->var instanceof Variable
			&& $assignExpr->var->name === 'this'
			&& $assignExpr->name instanceof Identifier
			&& $assignExpr->name->toString() === 'getComponent'
			&& $assignExpr->getArgs() !== []
		) {
			$nameArg = $assignExpr->getArgs()[0]->value;
			if ($nameArg instanceof String_) {
				$factoryName = 'createComponent' . ucfirst($nameArg->value);
				return $this->getMethodIfExists($contextClassName, $factoryName);
			}
		}

		// $var = $expr->method() → resolve class, get method
		if ($assignExpr instanceof MethodCall && $assignExpr->name instanceof Identifier) {
			$callerClass = $this->resolveExprClassName($assignExpr->var, $contextClassName);
			if ($callerClass !== null) {
				return $this->getMethodIfExists($callerClass, $assignExpr->name->toString());
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

		$containerClass = $this->reflectionProvider->getClass('Nette\Forms\Container');
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

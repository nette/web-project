<?php declare(strict_types=1);

namespace Nette\PHPStan\Assets;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use function count;


/**
 * Narrows return type of Registry::getMapper() from Mapper
 * to the specific mapper class based on NEON configuration.
 */
class GetMapperReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private MapperTypeResolver $resolver,
	) {
	}


	public function getClass(): string
	{
		return 'Nette\Assets\Registry';
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getMapper';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$args = $methodCall->getArgs();
		if ($args === []) {
			return $this->resolver->resolveMapper('default');
		}

		$idType = $scope->getType($args[0]->value);
		$constantStrings = $idType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		return $this->resolver->resolveMapper($constantStrings[0]->getValue());
	}
}

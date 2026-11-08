<?php declare(strict_types=1);

namespace Nette\PHPStan\Assets;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\{DynamicMethodReturnTypeExtension, Type};
use function count;


/**
 * Narrows return type of FilesystemMapper::getAsset() and ViteMapper::getAsset()
 * from Asset to the specific asset class based on file extension.
 * Registered twice in NEON — once per mapper class.
 */
class MapperGetAssetExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(
		private readonly MapperTypeResolver $resolver,
		/** @var class-string */
		private readonly string $className,
	) {
	}


	public function getClass(): string
	{
		return $this->className;
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getAsset';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		$refType = $scope->getType($args[0]->value);
		$constantStrings = $refType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		return $this->resolver->resolveAssetType($constantStrings[0]->getValue());
	}
}

<?php declare(strict_types=1);

namespace Nette\PHPStan\Assets;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;


/**
 * Narrows return type of Registry::getAsset() and Registry::tryGetAsset()
 * from Asset/?Asset to specific asset class based on mapper type and file extension.
 * Only narrows when the mapper is a known type (FilesystemMapper or ViteMapper).
 */
class RegistryGetAssetExtension implements DynamicMethodReturnTypeExtension
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
		return $methodReflection->getName() === 'getAsset'
			|| $methodReflection->getName() === 'tryGetAsset';
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

		$refType = $scope->getType($args[0]->value);
		$constantStrings = $refType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			return null;
		}

		[$mapperId, $assetPath] = $this->resolver->parseReference($constantStrings[0]->getValue());

		if (!$this->resolver->isKnownMapper($mapperId)) {
			return null;
		}

		$assetType = $this->resolver->resolveAssetType($assetPath);
		if ($assetType === null) {
			return null;
		}

		if ($methodReflection->getName() === 'tryGetAsset') {
			return TypeCombinator::addNull($assetType);
		}

		return $assetType;
	}
}

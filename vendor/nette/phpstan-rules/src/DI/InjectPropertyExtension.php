<?php declare(strict_types=1);

namespace Nette\PHPStan\DI;

use Nette\DI\Attributes\Inject;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;


/**
 * Treats properties marked with #[Nette\DI\Attributes\Inject] as written and
 * initialized, because Nette's dependency injection assigns them right after
 * the object is created. Without this, PHPStan reports such properties as
 * uninitialized or never written.
 */
final class InjectPropertyExtension implements ReadWritePropertiesExtension
{
	private const InjectAttribute = Inject::class;


	public function isAlwaysRead(ExtendedPropertyReflection $property, string $propertyName): bool
	{
		return false;
	}


	public function isAlwaysWritten(ExtendedPropertyReflection $property, string $propertyName): bool
	{
		return $this->isInjected($property);
	}


	public function isInitialized(ExtendedPropertyReflection $property, string $propertyName): bool
	{
		return $this->isInjected($property);
	}


	private function isInjected(ExtendedPropertyReflection $property): bool
	{
		foreach ($property->getAttributes() as $attribute) {
			if ($attribute->getName() === self::InjectAttribute) {
				return true;
			}
		}

		return false;
	}
}

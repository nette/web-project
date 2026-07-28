<?php declare(strict_types=1);

namespace Nette\PHPStan\Php;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function array_values;


/**
 * Resolves @property/@property-read tags declared on interfaces, which PHPStan core
 * ignores (the allowsDynamicProperties() gate skips annotation-based properties there).
 */
final class InterfacePropertyTagResolver
{
	/**
	 * Returns the readable type of an interface-declared virtual property, or null
	 * when any of the object types resolves the property natively or lacks the tag.
	 */
	public function resolve(Type $type, string $propertyName): ?Type
	{
		$reflections = $type->getObjectClassReflections();
		if ($reflections === []) {
			return null;
		}

		$types = [];
		foreach ($reflections as $reflection) {
			if ($reflection->hasProperty($propertyName)) {
				return null; // PHPStan resolves the property itself
			}

			$tagType = $this->findTagType($reflection, $propertyName);
			if ($tagType === null) {
				return null;
			}

			$types[] = $tagType;
		}

		return TypeCombinator::union(...$types);
	}


	private function findTagType(ClassReflection $reflection, string $propertyName): ?Type
	{
		foreach ([$reflection, ...array_values($reflection->getInterfaces())] as $candidate) {
			if (!$candidate->isInterface()) {
				continue;
			}

			$tag = $candidate->getPropertyTags()[$propertyName] ?? null;
			if ($tag?->isReadable()) {
				return $tag->getReadableType();
			}
		}

		return null;
	}
}

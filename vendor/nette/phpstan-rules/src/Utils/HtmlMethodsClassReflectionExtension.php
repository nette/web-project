<?php declare(strict_types=1);

namespace Nette\PHPStan\Utils;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use function in_array, strlen, substr;


/**
 * Resolves getXxx(), setXxx(), addXxx() magic methods on Nette\Utils\Html
 * that are handled by __call() but not declared via @method annotations.
 */
final class HtmlMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
	public function hasMethod(ClassReflection $classReflection, string $methodName): bool
	{
		if (!$classReflection->is('Nette\Utils\Html')) {
			return false;
		}

		return strlen($methodName) > 3
			&& in_array(substr($methodName, 0, 3), ['get', 'set', 'add'], true);
	}


	public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
	{
		$prefix = substr($methodName, 0, 3);
		return new HtmlCallMethodReflection($classReflection, $methodName, $prefix);
	}
}


/**
 * @internal
 */
final class HtmlCallMethodReflection implements MethodReflection
{
	public function __construct(
		private ClassReflection $declaringClass,
		private string $name,
		private string $prefix,
	) {
	}


	public function getDeclaringClass(): ClassReflection
	{
		return $this->declaringClass;
	}


	public function isStatic(): bool
	{
		return false;
	}


	public function isPrivate(): bool
	{
		return false;
	}


	public function isPublic(): bool
	{
		return true;
	}


	public function getDocComment(): ?string
	{
		return null;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getPrototype(): ClassMemberReflection
	{
		return $this;
	}


	public function getVariants(): array
	{
		if ($this->prefix === 'get') {
			return [
				new FunctionVariant(
					TemplateTypeMap::createEmpty(),
					TemplateTypeMap::createEmpty(),
					[],
					false,
					new MixedType,
				),
			];
		}

		return [
			new FunctionVariant(
				TemplateTypeMap::createEmpty(),
				TemplateTypeMap::createEmpty(),
				[],
				true,
				new StaticType($this->declaringClass),
			),
		];
	}


	public function isDeprecated(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}


	public function getDeprecatedDescription(): ?string
	{
		return null;
	}


	public function isFinal(): TrinaryLogic
	{
		return TrinaryLogic::createYes();
	}


	public function isInternal(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}


	public function getThrowType(): ?Type
	{
		return null;
	}


	public function hasSideEffects(): TrinaryLogic
	{
		return $this->prefix === 'get'
			? TrinaryLogic::createNo()
			: TrinaryLogic::createYes();
	}
}

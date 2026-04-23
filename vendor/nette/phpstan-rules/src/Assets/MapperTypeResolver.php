<?php declare(strict_types=1);

namespace Nette\PHPStan\Assets;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use function pathinfo, strpos, strtolower, substr;


/**
 * Resolves mapper IDs to mapper class types and asset references to asset class types.
 * Mapper IDs are resolved from a flat map configured in NEON.
 * Asset types are resolved from file extensions using hardcoded mapping mirroring Helpers::createAssetFromUrl().
 */
class MapperTypeResolver
{
	private const ExtensionToAssetClass = [
		'avif' => 'Nette\Assets\ImageAsset',
		'gif' => 'Nette\Assets\ImageAsset',
		'ico' => 'Nette\Assets\ImageAsset',
		'jpeg' => 'Nette\Assets\ImageAsset',
		'jpg' => 'Nette\Assets\ImageAsset',
		'png' => 'Nette\Assets\ImageAsset',
		'svg' => 'Nette\Assets\ImageAsset',
		'webp' => 'Nette\Assets\ImageAsset',
		'js' => 'Nette\Assets\ScriptAsset',
		'mjs' => 'Nette\Assets\ScriptAsset',
		'css' => 'Nette\Assets\StyleAsset',
		'aac' => 'Nette\Assets\AudioAsset',
		'flac' => 'Nette\Assets\AudioAsset',
		'm4a' => 'Nette\Assets\AudioAsset',
		'mp3' => 'Nette\Assets\AudioAsset',
		'ogg' => 'Nette\Assets\AudioAsset',
		'wav' => 'Nette\Assets\AudioAsset',
		'avi' => 'Nette\Assets\VideoAsset',
		'mkv' => 'Nette\Assets\VideoAsset',
		'mov' => 'Nette\Assets\VideoAsset',
		'mp4' => 'Nette\Assets\VideoAsset',
		'ogv' => 'Nette\Assets\VideoAsset',
		'webm' => 'Nette\Assets\VideoAsset',
		'woff' => 'Nette\Assets\FontAsset',
		'woff2' => 'Nette\Assets\FontAsset',
		'ttf' => 'Nette\Assets\FontAsset',
	];

	private const KnownMappers = [
		'Nette\Assets\FilesystemMapper',
		'Nette\Assets\ViteMapper',
	];


	/**
	 * @param array<string, string> $mapping mapper ID → type keyword ('file', 'vite') or FQCN
	 */
	public function __construct(
		private ReflectionProvider $reflectionProvider,
		private array $mapping = [],
	) {
	}


	/**
	 * Resolves a mapper ID to an ObjectType for the mapper class.
	 */
	public function resolveMapper(string $mapperId): ?ObjectType
	{
		if (!isset($this->mapping[$mapperId])) {
			return null;
		}

		$className = $this->inferMapperClass($this->mapping[$mapperId]);
		return $this->reflectionProvider->hasClass($className)
			? new ObjectType($className)
			: null;
	}


	private function inferMapperClass(string $value): string
	{
		return match ($value) {
			'file' => 'Nette\Assets\FilesystemMapper',
			'vite' => 'Nette\Assets\ViteMapper',
			default => $value,
		};
	}


	/**
	 * Checks whether the mapper for a given ID is a known mapper type
	 * (FilesystemMapper or ViteMapper) whose asset types can be narrowed.
	 */
	public function isKnownMapper(string $mapperId): bool
	{
		$mapperType = $this->resolveMapper($mapperId);
		if ($mapperType === null) {
			return false;
		}

		foreach (self::KnownMappers as $knownClass) {
			if ((new ObjectType($knownClass))->isSuperTypeOf($mapperType)->yes()) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Resolves an asset reference to an ObjectType based on its file extension.
	 */
	public function resolveAssetType(string $reference): ?ObjectType
	{
		$extension = strtolower(pathinfo($reference, PATHINFO_EXTENSION));
		return isset(self::ExtensionToAssetClass[$extension])
			? new ObjectType(self::ExtensionToAssetClass[$extension])
			: null;
	}


	/**
	 * Splits a qualified reference 'mapper:reference' into [mapperId, assetPath].
	 * @return array{string, string}
	 */
	public function parseReference(string $ref): array
	{
		$pos = strpos($ref, ':');
		return $pos !== false
			? [substr($ref, 0, $pos), substr($ref, $pos + 1)]
			: ['default', $ref];
	}
}

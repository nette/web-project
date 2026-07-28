<?php declare(strict_types=1);

namespace Nette\PHPStan\Assets;

use Nette\Assets\AudioAsset;
use Nette\Assets\FilesystemMapper;
use Nette\Assets\FontAsset, Nette\Assets\ImageAsset, Nette\Assets\ScriptAsset, Nette\Assets\StyleAsset;
use Nette\Assets\VideoAsset;
use Nette\Assets\ViteMapper;
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
		'avif' => ImageAsset::class,
		'gif' => ImageAsset::class,
		'ico' => ImageAsset::class,
		'jpeg' => ImageAsset::class,
		'jpg' => ImageAsset::class,
		'png' => ImageAsset::class,
		'svg' => ImageAsset::class,
		'webp' => ImageAsset::class,
		'js' => ScriptAsset::class,
		'mjs' => ScriptAsset::class,
		'css' => StyleAsset::class,
		'aac' => AudioAsset::class,
		'flac' => AudioAsset::class,
		'm4a' => AudioAsset::class,
		'mp3' => AudioAsset::class,
		'ogg' => AudioAsset::class,
		'wav' => AudioAsset::class,
		'avi' => VideoAsset::class,
		'mkv' => VideoAsset::class,
		'mov' => VideoAsset::class,
		'mp4' => VideoAsset::class,
		'ogv' => VideoAsset::class,
		'webm' => VideoAsset::class,
		'woff' => FontAsset::class,
		'woff2' => FontAsset::class,
		'ttf' => FontAsset::class,
	];

	private const KnownMappers = [
		FilesystemMapper::class,
		ViteMapper::class,
	];


	/**
	 * @param array<string, string> $mapping mapper ID → type keyword ('file', 'vite') or FQCN
	 */
	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly array $mapping = [],
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
			'file' => FilesystemMapper::class,
			'vite' => ViteMapper::class,
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

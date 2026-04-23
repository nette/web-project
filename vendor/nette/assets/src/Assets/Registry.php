<?php declare(strict_types=1);

namespace Nette\Assets;

use function array_key_exists, array_shift, count, hash, is_scalar, is_string, serialize;


/**
 * Manages a collection of named asset Mappers and provides a central point
 * for retrieving Assets using qualified references (mapper:reference).
 * Includes a simple cache for resolved assets.
 */
class Registry
{
	public const DefaultScope = 'default';
	private const MaxCacheSize = 100;

	/** @var array<string, Mapper> */
	private array $mappers = [];

	/** @var array<string, Asset> */
	private array $cache = [];


	/**
	 * Registers an asset mapper under the given identifier.
	 * @throws \InvalidArgumentException if the identifier is already in use
	 */
	public function addMapper(string $id, Mapper $mapper): void
	{
		if (isset($this->mappers[$id])) {
			throw new \InvalidArgumentException("Asset mapper '$id' is already registered");
		}
		$this->mappers[$id] = $mapper;
	}


	/**
	 * Returns the mapper registered under the given identifier.
	 * @throws \InvalidArgumentException if the identifier is unknown
	 */
	public function getMapper(string $id = self::DefaultScope): Mapper
	{
		return $this->mappers[$id] ?? throw new \InvalidArgumentException("Unknown asset mapper '$id'.");
	}


	/**
	 * Returns an Asset for the given qualified reference ('mapper:reference' or ['mapper', 'reference']).
	 * @param  string|array{?string, string}  $qualifiedRef
	 * @param  array<string, mixed>  $options  passed to the mapper
	 * @throws AssetNotFoundException when the asset cannot be found
	 */
	public function getAsset(string|array $qualifiedRef, array $options = []): Asset
	{
		[$mapper, $reference] = is_string($qualifiedRef)
			? Helpers::parseReference($qualifiedRef)
			: $qualifiedRef;

		$mapperDef = $mapper ?? self::DefaultScope;
		$reference = (string) $reference;
		$cacheKey = $this->generateCacheKey($mapperDef, $reference, $options);
		if ($cacheKey !== null && array_key_exists($cacheKey, $this->cache)) {
			return $this->cache[$cacheKey];
		}

		try {
			$asset = $this->getMapper($mapperDef)->getAsset($reference, $options);

			if (count($this->cache) >= self::MaxCacheSize) {
				array_shift($this->cache); // remove the oldest entry
			}

			if ($cacheKey !== null) {
				$this->cache[$cacheKey] = $asset;
			}
			return $asset;

		} catch (AssetNotFoundException $e) {
			throw $mapper ? $e->qualifyReference($mapperDef, $reference) : $e;
		}
	}


	/**
	 * Returns an Asset for the given qualified reference, or null if not found.
	 * @param  string|array{string|null, string}  $qualifiedRef
	 * @param  array<string, mixed>  $options  passed to the mapper
	 */
	public function tryGetAsset(string|array $qualifiedRef, array $options = []): ?Asset
	{
		try {
			return $this->getAsset($qualifiedRef, $options);
		} catch (AssetNotFoundException) {
			return null;
		}
	}


	/**
	 * Returns null when options contain non-scalar values (caching is disabled for such options).
	 * @param  array<string, mixed>  $options
	 */
	private function generateCacheKey(string $mapper, string $reference, array $options): ?string
	{
		foreach ($options as $item) {
			if ($item !== null && !is_scalar($item)) {
				return null;
			}
		}
		return $mapper . ':' . $reference . ($options ? ':' . hash('xxh128', serialize($options)) : '');
	}
}

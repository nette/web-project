<?php

declare(strict_types=1);

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
	 * Registers a new asset mapper under a specific identifier.
	 * @throws \InvalidArgumentException If the identifier is already in use.
	 */
	public function addMapper(string $id, Mapper $mapper): void
	{
		if (isset($this->mappers[$id])) {
			throw new \InvalidArgumentException("Asset mapper '$id' is already registered");
		}
		$this->mappers[$id] = $mapper;
	}


	/**
	 * Retrieves a registered asset mapper by its identifier.
	 * @throws \InvalidArgumentException If the requested mapper identifier is unknown.
	 */
	public function getMapper(string $id = self::DefaultScope): Mapper
	{
		return $this->mappers[$id] ?? throw new \InvalidArgumentException("Unknown asset mapper '$id'.");
	}


	/**
	 * Retrieves an Asset instance using a qualified reference. Accepts either 'mapper:reference' or ['mapper', 'reference'].
	 * Options passed directly to the underlying Mapper::getAsset() method.
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

			return $this->cache[$cacheKey] = $asset;
		} catch (AssetNotFoundException $e) {
			throw $mapper ? $e->qualifyReference($mapperDef, $reference) : $e;
		}
	}


	/**
	 * Attempts to retrieve an Asset instance using a qualified reference, but returns null if not found.
	 * Accepts either 'mapper:reference' or ['mapper', 'reference'].
	 * Options passed directly to the underlying Mapper::getAsset() method.
	 */
	public function tryGetAsset(string|array $qualifiedRef, array $options = []): ?Asset
	{
		try {
			return $this->getAsset($qualifiedRef, $options);
		} catch (AssetNotFoundException) {
			return null;
		}
	}


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

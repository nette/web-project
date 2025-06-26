<?php

declare(strict_types=1);

namespace Nette\Assets;


/**
 * Defines the contract for resolving asset references to Asset objects.
 * Implementations handle specific storage backends (filesystem, CDN, etc.).
 */
interface Mapper
{
	/**
	 * Retrieves an Asset instance for a given mapper-specific reference string.
	 * @throws AssetNotFoundException when the asset cannot be found
	 */
	public function getAsset(string $reference, array $options = []): Asset;
}

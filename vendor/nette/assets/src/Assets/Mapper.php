<?php declare(strict_types=1);

namespace Nette\Assets;


/**
 * Resolves asset references to Asset objects for a specific storage backend.
 */
interface Mapper
{
	/**
	 * Returns an Asset for the given reference.
	 * @param  array<string, mixed>  $options  mapper-specific options
	 * @throws AssetNotFoundException when the asset cannot be found
	 */
	public function getAsset(string $reference, array $options = []): Asset;
}

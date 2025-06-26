<?php

declare(strict_types=1);

namespace Nette\Assets;


/**
 * Base asset interface with minimal API.
 * @property-read string $url The public URL
 * @property-read ?string $file The local file path if available
 */
interface Asset
{
	//public string $url { get; }
	//public ?string $file { get; }

	/**
	 * Allows direct echoing of the object to get the URL.
	 */
	public function __toString(): string;
}

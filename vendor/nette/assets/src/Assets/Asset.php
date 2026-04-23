<?php declare(strict_types=1);

namespace Nette\Assets;


/**
 * Represents a web asset (image, script, stylesheet, font, etc.).
 * @property-read string $url The public URL
 * @property-read ?string $file The local file path if available
 */
interface Asset
{
	//public string $url { get; }
	//public ?string $file { get; }

	/**
	 * Returns the asset URL.
	 */
	public function __toString(): string;
}

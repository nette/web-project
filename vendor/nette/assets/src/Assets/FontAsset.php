<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Font asset.
 */
class FontAsset implements Asset, HtmlRenderable
{
	public readonly ?string $mimeType;


	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		?string $mimeType = null,
		/** SRI integrity hash */
		public readonly ?string $integrity = null,
	) {
		$this->mimeType = $mimeType ?? Helpers::guessMimeTypeFromExtension($file ?? $url);
	}


	public function __toString(): string
	{
		return $this->url;
	}


	public function getImportElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'font',
			'type' => $this->mimeType,
			'crossorigin' => true,
			'integrity' => $this->integrity,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return $this->getImportElement();
	}
}

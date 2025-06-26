<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Style asset.
 */
class StyleAsset implements Asset, HtmlRenderable
{
	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		/** Media query for the stylesheet */
		public readonly ?string $media = null,
		/** SRI integrity hash */
		public readonly ?string $integrity = null,
		public readonly string|bool|null $crossorigin = null,
	) {
	}


	public function __toString(): string
	{
		return $this->url;
	}


	public function getImportElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'stylesheet',
			'href' => $this->url,
			'media' => $this->media,
			'integrity' => $this->integrity,
			'crossorigin' => $this->crossorigin ?? (bool) $this->integrity,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', [
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'style',
			'crossorigin' => $this->crossorigin ?? (bool) $this->integrity,
		]);
	}
}

<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Script asset.
 */
class ScriptAsset implements Asset, HtmlRenderable
{
	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		public readonly ?string $type = null,
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
		return Html::el('script', array_filter([
			'src' => $this->url,
			'type' => $this->type,
			'integrity' => $this->integrity,
			'crossorigin' => $this->crossorigin ?? (bool) $this->integrity,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', array_filter($this->type === 'module'
			? [
				'rel' => 'modulepreload',
				'href' => $this->url,
				'crossorigin' => $this->crossorigin,
			]
			: [
				'rel' => 'preload',
				'href' => $this->url,
				'as' => 'script',
				'crossorigin' => $this->crossorigin,
			]));
	}
}

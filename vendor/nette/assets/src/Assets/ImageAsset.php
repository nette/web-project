<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Image asset.
 */
class ImageAsset implements Asset, HtmlRenderable
{
	use LazyLoad;

	public readonly ?int $width;
	public readonly ?int $height;


	public function __construct(
		public readonly string $url,
		public readonly ?string $mimeType = null,
		public readonly ?string $file = null,
		?int $width = null,
		?int $height = null,
		/** Alternative text for accessibility */
		public readonly ?string $alternative = null,
		public readonly bool $lazyLoad = false,
		public readonly int $density = 1,
		public readonly string|bool|null $crossorigin = null,
	) {
		if ($width === null && $height === null) {
			$this->lazyLoad(compact('width', 'height'), $this->getSize(...));
		} else {
			$this->width = $width;
			$this->height = $height;
		}
	}


	public function __toString(): string
	{
		return $this->url;
	}


	/**
	 * Retrieves image dimensions.
	 */
	private function getSize(): void
	{
		[$this->width, $this->height] = $this->file && ([$w, $h] = getimagesize($this->file))
			? [(int) round($w / $this->density), (int) round($h / $this->density)]
			: [null, null];
	}


	public function getImportElement(): Html
	{
		return Html::el('img', array_filter([
			'src' => $this->url,
			'width' => $this->width ? (string) $this->width : null,
			'height' => $this->height ? (string) $this->height : null,
			'alt' => $this->alternative,
			'loading' => $this->lazyLoad ? 'lazy' : null,
			'crossorigin' => $this->crossorigin,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'image',
			'type' => $this->mimeType,
			'crossorigin' => $this->crossorigin,
		], fn($value) => $value !== null));
	}
}

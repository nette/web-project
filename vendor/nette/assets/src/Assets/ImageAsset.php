<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;
use function array_filter, compact, getimagesize, round;


/**
 * Image asset.
 */
class ImageAsset implements Asset, HtmlRenderable
{
	use LazyLoad;

	public readonly ?int $width;
	public readonly ?int $height;
	public readonly ?string $mimeType;


	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		?int $width = null,
		?int $height = null,
		?string $mimeType = null,
		/** Alternative text for accessibility */
		public readonly ?string $alternative = null,
		public readonly bool $lazyLoad = false,
		public readonly int $density = 1,
		public readonly string|bool|null $crossorigin = null,
	) {
		$this->lazyLoad(compact('width', 'height', 'mimeType'), $this->getSize(...));
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
		$info = $this->file ? getimagesize($this->file) : null;
		if (!isset($this->mimeType)) {
			$this->mimeType = $info['mime'] ?? null;
		}
		// If only one dimension is provided, the other is set to null
		$info = isset($this->width) || isset($this->height) ? null : $info;
		if (!isset($this->width)) {
			$this->width = $info ? (int) round($info[0] / $this->density) : null;
		}
		if (!isset($this->height)) {
			$this->height = $info ? (int) round($info[1] / $this->density) : null;
		}
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

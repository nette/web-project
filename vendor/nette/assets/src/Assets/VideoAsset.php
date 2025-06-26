<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Video asset.
 */
class VideoAsset implements Asset, HtmlRenderable
{
	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		public readonly ?int $width = null,
		public readonly ?int $height = null,
		public readonly ?string $mimeType = null,
		/** Duration in seconds */
		public readonly ?float $duration = null,
		/** Poster image URL */
		public readonly ?string $poster = null,
		public readonly bool $autoPlay = false,
	) {
	}


	public function __toString(): string
	{
		return $this->url;
	}


	public function getImportElement(): Html
	{
		return Html::el('video', array_filter([
			'src' => $this->url,
			'width' => $this->width ? (string) $this->width : null,
			'height' => $this->height ? (string) $this->height : null,
			'type' => $this->mimeType,
			'poster' => $this->poster,
			'autoplay' => $this->autoPlay ? true : null,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'video',
			'type' => $this->mimeType,
		], fn($value) => $value !== null));
	}
}

<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Audio asset.
 */
class AudioAsset implements Asset, HtmlRenderable
{
	use LazyLoad;

	/** Duration in seconds */
	public readonly ?float $duration;
	public readonly ?string $mimeType;


	public function __construct(
		public readonly string $url,
		public readonly ?string $file = null,
		?string $mimeType = null,
		?float $duration = null,
	) {
		$this->mimeType = $mimeType ?? Helpers::guessMimeTypeFromExtension($file ?? $url);
		$this->lazyLoad(compact('duration'), fn() => $this->duration = $this->file
			? Helpers::guessMP3Duration($this->file)
			: null);
	}


	public function __toString(): string
	{
		return $this->url;
	}


	public function getImportElement(): Html
	{
		return Html::el('audio', [
			'src' => $this->url,
			'type' => $this->mimeType,
		]);
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'audio',
			'type' => $this->mimeType,
		], fn($value) => $value !== null));
	}
}

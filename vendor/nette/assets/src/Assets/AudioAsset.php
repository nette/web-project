<?php declare(strict_types=1);

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
		public readonly string|bool|null $crossorigin = null,
	) {
		$this->mimeType = $mimeType ?? Helpers::guessMimeTypeFromExtension($file ?? $url);
		$this->lazyLoad(compact('duration'), function () {
			$this->duration = $this->file && $this->mimeType === 'audio/mpeg'
				? Helpers::guessMP3Duration($this->file)
				: null;
		});
	}


	public function __toString(): string
	{
		return $this->url;
	}


	public function getImportElement(): Html
	{
		return Html::el('audio', array_filter([
			'src' => $this->url,
			'type' => $this->mimeType,
			'crossorigin' => $this->crossorigin,
		], fn($value) => $value !== null));
	}


	public function getPreloadElement(): Html
	{
		return Html::el('link', array_filter([
			'rel' => 'preload',
			'href' => $this->url,
			'as' => 'audio',
			'type' => $this->mimeType,
			'crossorigin' => $this->crossorigin,
		], fn($value) => $value !== null));
	}
}

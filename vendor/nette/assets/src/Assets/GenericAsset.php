<?php declare(strict_types=1);

namespace Nette\Assets;


/**
 * Asset for file types without a dedicated asset class.
 */
class GenericAsset implements Asset
{
	use LazyLoad;

	public readonly ?string $mimeType;


	public function __construct(
		public readonly string $url,
		?string $mimeType = null,
		public readonly ?string $file = null,
		public readonly ?string $media = null,
		public readonly ?string $integrity = null,
	) {
		$this->lazyLoad(compact('mimeType'), function () {
			$this->mimeType = $this->file ? (mime_content_type($this->file) ?: null) : null;
		});
	}


	public function __toString(): string
	{
		return $this->url;
	}
}

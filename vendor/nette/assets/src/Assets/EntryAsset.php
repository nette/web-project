<?php

declare(strict_types=1);

namespace Nette\Assets;


/**
 * Entry point asset implementation that can represent both script and style entry points.
 */
class EntryAsset extends ScriptAsset
{
	public function __construct(
		public readonly string $url,
		/** @var Asset[] */
		public array $imports = [],
		/** @var Asset[] */
		public array $preloads = [],
		public readonly ?string $type = 'module',
		public readonly ?string $file = null,
		public readonly ?string $integrity = null,
		public readonly string|bool|null $crossorigin = null,
	) {
	}
}

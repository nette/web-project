<?php declare(strict_types=1);

namespace Nette\Assets;


/**
 * Vite entry point asset that carries its CSS imports and JS preloads as dependencies.
 */
class EntryAsset extends ScriptAsset
{
	public function __construct(
		public readonly string $url,
		/** @var list<HtmlRenderable> */
		public array $imports = [],
		/** @var list<HtmlRenderable> */
		public array $preloads = [],
		public readonly ?string $type = 'module',
		public readonly ?string $file = null,
		public readonly ?string $integrity = null,
		public readonly string|bool|null $crossorigin = null,
	) {
	}
}

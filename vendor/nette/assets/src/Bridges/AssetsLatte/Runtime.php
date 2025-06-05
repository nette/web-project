<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\AssetsLatte;

use Nette;
use Nette\Assets\Asset;
use Nette\Assets\EntryAsset;
use Nette\Assets\HtmlRenderable;
use Nette\Assets\Registry;


/**
 * Runtime helpers for Latte.
 * @internal
 */
class Runtime
{
	public function __construct(
		private readonly Registry $registry,
	) {
	}


	public function resolve(string|array|Asset|null $asset, array $options, bool $try): ?Asset
	{
		return match (true) {
			$asset instanceof Asset => $asset,
			$asset === null => $try ? null : throw new Nette\InvalidArgumentException('Asset cannot be null.'),
			$try => $this->registry->tryGetAsset($asset, $options),
			default => $this->registry->getAsset($asset, $options),
		};
	}


	public function renderAsset(Asset $asset): string
	{
		if (!$asset instanceof HtmlRenderable) {
			throw new Nette\InvalidArgumentException('This asset type cannot be rendered as HTML.');
		}

		$res = (string) $asset->getImportElement();

		if ($asset instanceof EntryAsset) {
			foreach ($asset->preloads as $dep) {
				$res .= $dep->getPreloadElement();
			}
			foreach ($asset->imports as $dep) {
				$res .= $dep->getImportElement();
			}
		}

		return $res;
	}


	public function renderAssetPreload(Asset $asset): string
	{
		if (!$asset instanceof HtmlRenderable) {
			throw new Nette\InvalidArgumentException('This asset type cannot be preloaded.');
		}

		return (string) $asset->getPreloadElement();
	}


	public function renderAttributes(Asset $asset, string $tagName, array $usedAttributes): string
	{
		if (!$asset instanceof HtmlRenderable) {
			throw new Nette\InvalidArgumentException('This asset type cannot be rendered with attributes.');
		}

		$el = $asset->getImportElement();
		if ($el->getName() !== $tagName) {
			if ($tagName === 'link') {
				$el = $asset->getPreloadElement();
			} elseif ($tagName === 'a') {
				$el = Nette\Utils\Html::el('a', ['href' => $el->src]);
			} else {
				throw new Nette\InvalidArgumentException("Tag <$tagName> is not allowed for this asset. Use <{$el->getName()}> instead.");
			}
		}

		$this->completeDimensions($el, $usedAttributes);

		$el->attrs = array_diff_key($el->attrs, $usedAttributes);
		return $el->attributes();
	}


	private function completeDimensions(Nette\Utils\Html $el, array $usedAttributes): void
	{
		$width = $usedAttributes['width'] ?? null;
		$height = $usedAttributes['height'] ?? null;
		if (isset($width) xor isset($height)) {
			if (empty($el->attrs['width']) || empty($el->attrs['height'])) {
				// unknown ratio
			} elseif (is_string($width)) {
				$el->attrs['height'] = (string) round($width / $el->attrs['width'] * $el->attrs['height']);
				return;
			} elseif (is_string($height)) {
				$el->attrs['width'] = (string) round($height / $el->attrs['height'] * $el->attrs['width']);
				return;
			}
		}
		if (isset($width) || isset($height)) {
			unset($el->attrs['width'], $el->attrs['height']);
		}
	}
}

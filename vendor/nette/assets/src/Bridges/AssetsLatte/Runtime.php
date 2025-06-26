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
use Nette\Utils\Html;
use function array_diff_key, headers_list, is_string, preg_match, round;


/**
 * Runtime helpers for Latte.
 * @internal
 */
class Runtime
{
	public function __construct(
		private readonly Registry $registry,
		private string|false|null $nonce = null,
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

		$res = (string) $this->applyNonce($asset->getImportElement());

		if ($asset instanceof EntryAsset) {
			foreach ($asset->preloads as $dep) {
				$res .= $this->applyNonce($dep->getPreloadElement());
			}
			foreach ($asset->imports as $dep) {
				$res .= $this->applyNonce($dep->getImportElement());
			}
		}

		return $res;
	}


	public function renderAssetPreload(Asset $asset): string
	{
		if (!$asset instanceof HtmlRenderable) {
			throw new Nette\InvalidArgumentException('This asset type cannot be preloaded.');
		}

		return (string) $this->applyNonce($asset->getPreloadElement());
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
				$el = Html::el('a', ['href' => $el->src]);
			} else {
				throw new Nette\InvalidArgumentException("Tag <$tagName> is not allowed for this asset. Use <{$el->getName()}> instead.");
			}
		}

		$this->applyNonce($el);
		$this->completeDimensions($el, $usedAttributes);

		$el->attrs = array_diff_key($el->attrs, $usedAttributes);
		return $el->attributes();
	}


	private function completeDimensions(Html $el, array $usedAttributes): void
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


	private function applyNonce(Html $el): Html
	{
		if (isset(['script' => 1, 'link' => 1, 'style' => 1][$el->getName()])) {
			$el->setAttribute('nonce', $this->nonce ??= $this->findNonce());
		}
		return $el;
	}


	private function findNonce(): string|false
	{
		foreach (headers_list() as $header) {
			if (preg_match('/^Content-Security-Policy(?:-Report-Only)?:.*\'nonce-([^\']+)\'/i', $header, $m)) {
				return $m[1];
			}
		}
		return false;
	}
}

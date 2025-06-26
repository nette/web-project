<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Interface for assets that can be rendered as HTML elements.
 */
interface HtmlRenderable extends Asset
{
	/**
	 * Returns HTML tag name and attributes for rendering the asset.
	 */
	public function getImportElement(): Html;

	/**
	 * Returns HTML tag name and attributes for preloading the asset.
	 */
	public function getPreloadElement(): Html;
}

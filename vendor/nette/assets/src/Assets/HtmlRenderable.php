<?php declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\Html;


/**
 * Asset renderable as an HTML element.
 */
interface HtmlRenderable extends Asset
{
	/**
	 * Returns the HTML element used to import the asset (img, script, link rel="stylesheet", etc.).
	 */
	public function getImportElement(): Html;

	/**
	 * Returns the HTML element used to preload the asset (link rel="preload" or rel="modulepreload").
	 */
	public function getPreloadElement(): Html;
}

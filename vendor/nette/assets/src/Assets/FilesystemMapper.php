<?php declare(strict_types=1);

namespace Nette\Assets;

use function filemtime, is_file, is_int, str_contains;


/**
 * Maps asset references to files within a specified local directory.
 * Supports configurable versioning and optional extension auto-detection.
 */
class FilesystemMapper implements Mapper
{
	private const OptionVersion = 'version';


	public function __construct(
		protected readonly string $baseUrl,
		protected readonly string $basePath,
		/** @var list<string> file extensions to try (without dot) */
		protected readonly array $extensions = [],
		protected readonly bool $versioning = true,
	) {
	}


	/**
	 * Returns the asset for the given relative reference.
	 * Tries configured extensions in order and appends a version query parameter if enabled.
	 * Supported option: 'version' => bool (overrides the mapper-level versioning setting)
	 */
	public function getAsset(string $reference, array $options = []): Asset
	{
		Helpers::checkOptions($options, [self::OptionVersion]);
		$path = $this->basePath . '/' . $reference;
		$path .= $ext = $this->findExtension($path);

		if (!is_file($path)) {
			throw new AssetNotFoundException("Asset file '$reference' not found at path: '$path'");
		}

		$url = $this->baseUrl . '/' . $reference . $ext;
		if ($options[self::OptionVersion] ?? $this->versioning) {
			$url = $this->applyVersion($url, $path);
		}
		return Helpers::createAssetFromUrl($url, $path);
	}


	/**
	 * Appends a ?v={filemtime} version parameter to the URL.
	 */
	protected function applyVersion(string $url, string $path): string
	{
		if (is_int($version = filemtime($path))) {
			$url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
		}
		return $url;
	}


	/**
	 * Returns the first matching file extension (with dot) from the configured list, or '' if none match.
	 */
	private function findExtension(string $basePath): string
	{
		$defaultExt = null;
		foreach ($this->extensions as $ext) {
			if ($ext === '') {
				$defaultExt = '';
			} else {
				$ext = '.' . $ext;
				$defaultExt ??= $ext;
			}
			if (is_file($basePath . $ext)) {
				return $ext;
			}
		}

		return $defaultExt ?? '';
	}


	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}


	public function getBasePath(): string
	{
		return $this->basePath;
	}
}

<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use function array_filter, array_values, is_array, preg_match, str_starts_with;


/**
 * Maps asset references to Vite-generated files using a Vite manifest.json.
 * Supports both development mode (Vite dev server) and production mode.
 */
class ViteMapper implements Mapper
{
	private array $chunks;
	private array $dependencies = [];


	public function __construct(
		private readonly string $baseUrl,
		private readonly string $basePath,
		private readonly ?string $manifestPath = null,
		private readonly ?string $devServer = null,
		private readonly ?Mapper $publicMapper = null,
	) {
		if ($devServer !== null && !str_starts_with($devServer, 'http')) {
			throw new \InvalidArgumentException("Vite devServer must be absolute URL, '$devServer' given");
		}
	}


	/**
	 * Retrieves an Asset for a given Vite entry point.
	 * @throws AssetNotFoundException when the asset cannot be found in the manifest
	 */
	public function getAsset(string $reference, array $options = []): Asset
	{
		Helpers::checkOptions($options);

		if ($this->devServer) {
			return $this->createDevelopmentAsset($reference);
		}

		$this->chunks ??= $this->readChunks();

		if (isset($this->chunks[$reference])) {
			return $this->createProductionAsset($reference);

		} elseif ($this->publicMapper) {
			return $this->publicMapper->getAsset($reference);

		} else {
			throw new AssetNotFoundException("File '$reference' not found in Vite manifest");
		}
	}


	private function createProductionAsset(string $reference): Asset
	{
		$chunk = $this->chunks[$reference];
		$entry = isset($chunk['isEntry']) || isset($chunk['isDynamicEntry']);
		if (str_starts_with($reference, '_') && !$entry) {
			throw new AssetNotFoundException("Cannot directly access internal chunk '$reference'");
		}

		$dependencies = $this->collectDependencies($reference);
		unset($dependencies[$chunk['file']]);

		return $dependencies
			? new EntryAsset(
				url: $this->baseUrl . '/' . $chunk['file'],
				file: $this->basePath . '/' . $chunk['file'],
				imports: array_values(array_filter($dependencies, fn($asset) => $asset instanceof StyleAsset)),
				preloads: array_values(array_filter($dependencies, fn($asset) => $asset instanceof ScriptAsset)),
				crossorigin: true,
			)
			: Helpers::createAssetFromUrl(
				$this->baseUrl . '/' . $chunk['file'],
				$this->basePath . '/' . $chunk['file'],
				['crossorigin' => true],
			);
	}


	private function createDevelopmentAsset(string $reference): Asset
	{
		$url = $this->devServer . '/' . $reference;
		return match (1) {
			preg_match('~\.(jsx?|mjs|tsx?)$~i', $reference) => new EntryAsset(
				url: $url,
				imports: [new ScriptAsset($this->devServer . '/@vite/client', type: 'module')],
			),
			preg_match('~\.(sass|scss)$~i', $reference) => new StyleAsset($url),
			default => Helpers::createAssetFromUrl($url),
		};
	}


	/**
	 * Recursively collects all imports (including nested) from a chunk.
	 */
	private function collectDependencies(string $chunkId): array
	{
		$deps = &$this->dependencies[$chunkId];
		if ($deps === null) {
			$deps = [];
			$chunk = $this->chunks[$chunkId] ?? [];
			foreach ($chunk['css'] ?? [] as $file) {
				$deps[$file] = Helpers::createAssetFromUrl(
					$this->baseUrl . '/' . $file,
					$this->basePath . '/' . $file,
					['crossorigin' => true],
				);
			}
			foreach ($chunk['imports'] ?? [] as $id) {
				$file = $this->chunks[$id]['file'];
				$deps[$file] = Helpers::createAssetFromUrl(
					$this->baseUrl . '/' . $file,
					$this->basePath . '/' . $file,
					['type' => 'module', 'crossorigin' => true],
				);
				$deps += $this->collectDependencies($id);
			}
		}
		return $deps;
	}


	private function readChunks(): array
	{
		$path = $this->manifestPath ?? $this->basePath . '/.vite/manifest.json';
		try {
			$res = Json::decode(FileSystem::read($path), forceArrays: true);
		} catch (\Throwable $e) {
			throw new \RuntimeException("Failed to read Vite manifest from '$path'. Did you run 'npm run build'?", 0, $e);
		}
		if (!is_array($res)) {
			throw new \RuntimeException("Invalid Vite manifest format in '$path'");
		}
		return $res;
	}


	/**
	 * Returns the base URL for this mapper.
	 */
	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}


	/**
	 * Returns the base path for this mapper.
	 */
	public function getBasePath(): string
	{
		return $this->basePath;
	}
}

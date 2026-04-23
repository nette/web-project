<?php declare(strict_types=1);

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
	/** @var array<string, array{file: string, isEntry?: bool, isDynamicEntry?: bool, css?: list<string>, imports?: list<string>}> */
	private array $chunks;

	/** @var array<string, ?array<string, Asset>> dependencies cache keyed by chunk ID; null = not yet computed */
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
	 * Returns an Asset for the given Vite entry point or public directory file.
	 * @throws AssetNotFoundException when the asset is not found in the manifest or public directory
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
	 * @return array<string, Asset>
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


	/**
	 * @return array<string, array{file: string, isEntry?: bool, isDynamicEntry?: bool, css?: list<string>, imports?: list<string>}>
	 */
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


	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}


	public function getBasePath(): string
	{
		return $this->basePath;
	}
}

<?php

declare(strict_types=1);

namespace Nette\Bridges\AssetsDI;

use Nette;
use Nette\Assets\FilesystemMapper;
use Nette\Assets\Registry;
use Nette\Assets\ViteMapper;
use Nette\Bridges\AssetsLatte\LatteExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use function is_string;


/**
 * Dependency injection extension that integrates asset management into Nette application.
 * Provides configuration of asset mappers and their mapping to URL paths.
 */
final class DIExtension extends Nette\DI\CompilerExtension
{
	private const VitePort = 5173;

	private int $needVariable;


	public function __construct(
		private string|Nette\Schema\DynamicParameter|null $baseUrl = null,
		private ?string $basePath = null,
		private bool $debugMode = false,
	) {
	}


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'basePath' => Expect::string()->dynamic(),
			'baseUrl' => Expect::string()->dynamic(),
			'versioning' => Expect::bool(),
			'mapping' => Expect::arrayOf(
				Expect::anyOf(
					Expect::string(),
					Expect::structure([
						'type' => Expect::string(),
						'path' => Expect::string()->dynamic(),
						'url' => Expect::string()->dynamic(),
						'extension' => Expect::anyOf(Expect::string(), Expect::arrayOf('string')),
						'versioning' => Expect::bool(),
						'manifest' => Expect::string()->dynamic(),
						'devServer' => Expect::anyOf(Expect::string(), Expect::bool())->default(true),
					]),
					Expect::type(Statement::class),
				),
			)->default(['default' => 'assets']),
		]);
	}


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$registry = $builder->addDefinition($this->prefix('registry'))
			->setFactory(Registry::class);

		$this->needVariable = 0;
		$this->basePath = $this->config->basePath ?? $this->basePath ?? null;

		foreach ($this->config->mapping as $scope => $item) {
			if (is_string($item)) {
				$mapper = str_contains($item, '\\')
					? new Statement($item)
					: $this->createFileMapper((object) ['path' => $item]);

			} elseif (!$item instanceof \stdClass) {
				$mapper = $item;

			} elseif (($item->type ?? null) === 'vite') {
				$mapper = $this->createViteMapper($item);

			} else {
				$mapper = $this->createFileMapper($item);
			}

			if ($this->needVariable === 1) {
				$baseUrl = $this->config->baseUrl ?? $this->baseUrl ?? throw new \LogicException("Assets: 'baseUrl' is not defined");
				$registry->addSetup('$baseUrl = new Nette\Http\UrlImmutable(?)', [new Statement("rtrim(?, '/') . '/'", [$baseUrl])]);
			}

			$registry->addSetup('addMapper', [$scope, $mapper]);
		}
	}


	private function createFileMapper(\stdClass $config): Statement
	{
		$this->needVariable++;
		return new Statement(FilesystemMapper::class, [
			'baseUrl' => $this->resolveUrl($config),
			'basePath' => $this->resolvePath($config),
			'extensions' => (array) ($config->extension ?? null),
			'versioning' => $config->versioning ?? $this->config->versioning ?? true,
		]);
	}


	private function createViteMapper(\stdClass $config): Statement
	{
		return new Statement(ViteMapper::class, [
			'baseUrl' => $this->resolveUrl($config),
			'basePath' => new Statement('$path = ?', [$this->resolvePath($config)]),
			'manifestPath' => $config->manifest ? new Statement('Nette\Utils\FileSystem::resolvePath($path, ?)', [$config->manifest]) : null,
			'devServer' => $this->resolveDevServer($config),
			'publicMapper' => $this->createFileMapper($config),
		]);
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		if ($name = $builder->getByType(Nette\Bridges\ApplicationLatte\LatteFactory::class)) {
			$builder->getDefinition($name)
				->getResultDefinition()
				->addSetup('addExtension', [new Statement(LatteExtension::class)]);
		}
	}


	private function resolvePath(\stdClass $config): string|Statement
	{
		$path = isset($this->basePath, $config->path)
			? new Statement('Nette\Utils\FileSystem::resolvePath(?, ?)', [$this->basePath, $config->path])
			: $config->path ?? $this->basePath ?? throw new \LogicException("Assets: 'basePath' is not defined");
		return new Statement("rtrim(?, '\\/')", [$path]);
	}


	private function resolveUrl(\stdClass $config): Statement
	{
		$url = new Statement('$baseUrl->resolve(?)->getAbsoluteUrl()', [$config->url ?? $config->path ?? '']);
		return new Statement("rtrim(?, '/')", [$url]);
	}


	private function resolveDevServer(\stdClass $config): Statement|string|null
	{
		return match (true) {
			!$this->debugMode || !$config->devServer => null,
			$config->devServer === true => new Statement('Nette\Assets\Helpers::detectDevServer(? . "/.vite/nette.json")', [$this->resolvePath($config)]),
			default => rtrim($config->devServer, '/'),
		};
	}
}

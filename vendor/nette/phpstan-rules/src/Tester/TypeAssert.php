<?php declare(strict_types=1);

namespace Nette\PHPStan\Tester;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\ExtensionInstaller\GeneratedConfig;
use PHPStan\File\FileHelper;
use PHPStan\Type\VerbosityLevel;
use Tester\Assert;
use function array_merge, count, hash, implode, in_array, is_file, is_string, sprintf, strtolower, sys_get_temp_dir;


/**
 * Verifies assertType() calls in a PHP file against PHPStan's type inference using Nette Tester.
 */
class TypeAssert
{
	/** @var array<string, Container> */
	private static array $containers = [];


	/**
	 * Gathers assertType() calls from a PHP file and verifies them against PHPStan's type inference.
	 * @param list<string> $configFiles
	 */
	public static function assertTypes(string $file, array $configFiles = []): void
	{
		$file = realpath($file);
		Assert::type('string', $file);
		$container = self::createContainer($configFiles, $file);

		$fileHelper = $container->getByType(FileHelper::class);
		$file = $fileHelper->normalizePath($file);

		$parser = $container->getService('defaultAnalysisParser');
		$nodeScopeResolver = $container->getByType(NodeScopeResolver::class);
		$scopeFactory = $container->getByType(ScopeFactory::class);

		$pathRoutingParser = $container->getService('pathRoutingParser');
		$pathRoutingParser->setAnalysedFiles([$file]);
		$nodeScopeResolver->setAnalysedFiles([$file]);
		$scope = $scopeFactory->create(ScopeContext::create($file));

		$asserts = [];
		$nodeScopeResolver->processNodes(
			$parser->parseFile($file),
			$scope,
			static function (Node $node, Scope $scope) use (&$asserts): void {
				$assert = self::processAssertTypeCall($node, $scope);
				if ($assert !== null) {
					$asserts[] = $assert;
				}
			},
		);

		if (count($asserts) === 0) {
			Assert::fail(sprintf('File %s does not contain any assertType() calls.', $file));
		}

		foreach ($asserts as $assert) {
			Assert::same(
				$assert['expected'],
				$assert['actual'],
				sprintf('on line %d', $assert['line']),
			);
		}
	}


	/**
	 * Analyses a PHP file and verifies that PHPStan reports no errors.
	 * @param list<string> $configFiles
	 */
	public static function assertNoErrors(string $file, array $configFiles = []): void
	{
		$container = self::createContainer($configFiles);

		$fileHelper = $container->getByType(FileHelper::class);
		$file = $fileHelper->normalizePath($file);

		$container->getService('pathRoutingParser')->setAnalysedFiles([$file]);

		$analyser = $container->getByType(Analyser::class);
		$result = $analyser->analyse([$file]);

		$errors = array_map(
			static fn($e) => $e->getIdentifier() . ' on line ' . $e->getLine(),
			$result->getErrors(),
		);
		Assert::same([], $errors);
	}


	/**
	 * @return array{expected: string, actual: string, line: int}|null
	 */
	private static function processAssertTypeCall(Node $node, Scope $scope): ?array
	{
		if (!$node instanceof Node\Expr\FuncCall) {
			return null;
		}

		$nameNode = $node->name;
		if (!$nameNode instanceof Name) {
			return null;
		}

		$functionName = $nameNode->toString();

		if (in_array(strtolower($functionName), ['asserttype', 'assertnativetype'], true)) {
			Assert::fail(sprintf('Missing use statement for %s() on line %d.', $functionName, $node->getStartLine()));
		}

		if ($functionName !== 'PHPStan\Testing\assertType') {
			return null;
		}

		if (count($node->getArgs()) !== 2) {
			Assert::fail(sprintf('Wrong assertType() call on line %d.', $node->getStartLine()));
		}

		$expectedType = $scope->getType($node->getArgs()[0]->value);
		$constantStrings = $expectedType->getConstantStrings();
		if (count($constantStrings) !== 1) {
			Assert::fail(sprintf(
				'Expected type must be a literal string, %s given on line %d.',
				$expectedType->describe(VerbosityLevel::precise()),
				$node->getStartLine(),
			));
			return null;
		}

		$actualType = $scope->getType($node->getArgs()[1]->value);
		return [
			'expected' => $constantStrings[0]->getValue(),
			'actual' => $actualType->describe(VerbosityLevel::precise()),
			'line' => $node->getStartLine(),
		];
	}


	/**
	 * Discovers extension config files from phpstan/extension-installer or falls back to own extension.neon.
	 * @return list<string>
	 */
	private static function getDefaultConfigFiles(): array
	{
		if (class_exists(GeneratedConfig::class)) {
			$configFiles = [];
			foreach (GeneratedConfig::EXTENSIONS as $extensionConfig) {
				foreach ($extensionConfig['extra']['includes'] ?? [] as $include) {
					if (!is_string($include)) {
						continue;
					}

					$path = $extensionConfig['install_path'] . '/' . $include;
					if (is_file($path)) {
						$configFiles[] = $path;
					}
				}
			}

			return $configFiles;
		}

		return [__DIR__ . '/../../extension.neon'];
	}


	/**
	 * @param list<string> $configFiles
	 */
	private static function createContainer(array $configFiles, ?string $analysedFile = null): Container
	{
		$configFiles = $configFiles ?: self::getDefaultConfigFiles();
		$cacheKey = hash('sha256', implode("\n", $configFiles) . "\n" . $analysedFile);

		if (isset(self::$containers[$cacheKey])) {
			ContainerFactory::postInitializeContainer(self::$containers[$cacheKey]);
			return self::$containers[$cacheKey];
		}

		$tmpDir = sys_get_temp_dir() . '/phpstan-tests';
		@mkdir($tmpDir, 0o777, true); // @ - directory may already exist

		$containerFactory = new ContainerFactory((string) getcwd());

		$allConfigFiles = array_merge(
			[$containerFactory->getConfigDirectory() . '/config.level8.neon'],
			$configFiles,
		);

		$container = $containerFactory->create($tmpDir, $allConfigFiles, $analysedFile ? [$analysedFile] : []);
		self::$containers[$cacheKey] = $container;

		foreach ($container->getParameter('bootstrapFiles') as $bootstrapFile) {
			(static function (string $file): void {
				require_once $file;
			})($bootstrapFile);
		}

		return $container;
	}
}

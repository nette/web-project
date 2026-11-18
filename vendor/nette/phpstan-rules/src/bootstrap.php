<?php declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// Old Nette class names are aliases; their declarations under `if (false)` only carry @deprecated,
// so the aliases are loaded for PHPStan to see the classes they stand for
if (!class_exists(ClassLoader::class)) {
	return;
}

foreach (ClassLoader::getRegisteredLoaders() as $loader) {
	foreach ($loader->getClassMap() as $class => $file) {
		if (str_starts_with($class, 'Nette\\') && str_starts_with(basename($file), 'compatibility')) {
			try {
				class_exists($class);
			} catch (Throwable) {
				// the class the alias stands for needs a package that is not installed; the rest of the file stays unloaded
			}
		}
	}
}

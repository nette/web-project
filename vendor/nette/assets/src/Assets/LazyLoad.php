<?php

declare(strict_types=1);

namespace Nette\Assets;


/**
 * Lazy-loading of properties as a workaround for PHP < 8.4.
 * @internal
 */
trait LazyLoad
{
	/** @var \Closure[] */
	private array $lazyLoaders = [];


	/**
	 * Sets up lazy loading for specified properties.
	 * @param mixed[] $props
	 */
	private function lazyLoad(array $props, \Closure $loader): void
	{
		foreach ($props as $name => $value) {
			if ($value === null) {
				unset($this->$name);
				$this->lazyLoaders[$name] = $loader;
			} else {
				$this->$name = $value;
			}
		}
	}


	public function __get(string $name): mixed
	{
		if ($loader = $this->lazyLoaders[$name] ?? null) {
			$loader();
		}
		return $this->$name;
	}
}

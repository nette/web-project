<?php

declare(strict_types=1);

/** @var Nette\DI\Container $container */
$container = require __DIR__ . '/../app/bootstrap.php';

$container->getByType(Nette\Application\Application::class)
	->run();

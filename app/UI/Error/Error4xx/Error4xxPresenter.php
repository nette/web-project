<?php

declare(strict_types=1);

namespace App\UI\Error\Error4xx;

use Nette;
use Nette\Application\Attributes\Requires;


/**
 * Handles 4xx HTTP error responses.
 */
#[Requires(methods: '*', forward: true)]
final class Error4xxPresenter extends Nette\Application\UI\Presenter
{
	public function renderDefault(Nette\Application\BadRequestException $exception): void
	{
		// renders the appropriate error template based on the HTTP status code
		$code = $exception->getCode();
		$file = is_file($file = __DIR__ . "/$code.latte")
			? $file
			: __DIR__ . '/4xx.latte';
		$this->template->httpCode = $code;
		$this->template->setFile($file);
	}
}

<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


/**
 * Handles 4xx HTTP error responses.
 */
final class Error4xxPresenter extends Nette\Application\UI\Presenter
{
	public function startup(): void
	{
		parent::startup();
		// ensure the request is a forward (internal redirect)
		if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
			$this->error();
		}
	}


	public function renderDefault(Nette\Application\BadRequestException $exception): void
	{
		// load the template corresponding to the HTTP code
		$file = __DIR__ . "/templates/Error/{$exception->getCode()}.latte";
		$this->template->setFile(is_file($file) ? $file : __DIR__ . '/templates/Error/4xx.latte');
	}
}

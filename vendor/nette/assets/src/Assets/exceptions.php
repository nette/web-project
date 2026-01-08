<?php

declare(strict_types=1);

namespace Nette\Assets;


/**
 * Asset cannot be found by a mapper.
 */
class AssetNotFoundException extends \Exception
{
	/** @internal */
	public function qualifyReference(string $mapper, string $reference): self
	{
		if ($mapper !== '') {
			$this->message = str_replace("'$reference'", "'$mapper:$reference'", $this->message);
		}
		return $this;
	}
}

<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\PhpGenerator;


/**
 * Definition of a class constant.
 */
final class Constant
{
	use Traits\NameAware;
	use Traits\VisibilityAware;
	use Traits\CommentAware;
	use Traits\AttributeAware;

	private mixed $value;
	private bool $final = false;
	private ?string $type = null;


	public function setValue(mixed $val): static
	{
		$this->value = $val;
		return $this;
	}


	public function getValue(): mixed
	{
		return $this->value;
	}


	public function setFinal(bool $state = true): static
	{
		$this->final = $state;
		return $this;
	}


	public function isFinal(): bool
	{
		return $this->final;
	}


	public function setType(?string $type): static
	{
		Helpers::validateType($type);
		$this->type = $type;
		return $this;
	}


	public function getType(): ?string
	{
		return $this->type;
	}
}

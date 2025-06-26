<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\AssetsLatte\Nodes;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;


/**
 * {asset ...}
 * {preload ...}
 */
class AssetNode extends StatementNode
{
	public ExpressionNode $name;
	public ArrayNode $attributes;
	public bool $optional;
	public bool $preload;


	public static function create(Tag $tag): static
	{
		$tag->outputMode = $tag::OutputKeepIndentation;
		$tag->expectArguments();

		$node = new static;
		$node->optional = str_starts_with($tag->parser->text, '?') && $tag->parser->stream->tryConsume('?');
		$node->name = $tag->parser->parseExpression();
		$node->attributes = $tag->parser->stream->tryConsume(',')
			? $tag->parser->parseArguments()
			: new ArrayNode;
		$node->preload = str_starts_with($tag->name, 'preload');
		return $node;
	}


	public function print(PrintContext $context): string
	{
		$escaper = $context->getEscaper();
		$inAttr = $escaper->getState() === $escaper::HtmlAttribute;
		return $context->format(
			<<<'XX'
				if ($ʟ_tmp = $this->global->assets->resolve(%node, %node, %dump)) %line {
					echo %raw($ʟ_tmp);
				}
				XX,
			$this->name,
			$this->attributes,
			$this->optional,
			$this->position,
			$inAttr ? '' : ('$this->global->assets->' . ($this->preload ? 'renderAssetPreload' : 'renderAsset')),
		);
	}


	public function &getIterator(): \Generator
	{
		yield $this->name;
		yield $this->attributes;
	}
}

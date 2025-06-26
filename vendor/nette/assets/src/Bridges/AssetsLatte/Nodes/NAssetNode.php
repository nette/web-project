<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\AssetsLatte\Nodes;

use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;


/**
 * n:asset and n:tryasset in <link>, <script>, <img>, <video>, <audio>, <a>
 */
final class NAssetNode extends StatementNode
{
	public ExpressionNode $name;
	public ArrayNode $attributes;
	public AreaNode $content;
	public bool $optional;


	public static function create(Tag $tag): \Generator
	{
		$tag->expectArguments();

		$node = $tag->node = new static;
		$node->name = $tag->parser->parseUnquotedStringOrExpression();
		$node->attributes = $tag->parser->stream->tryConsume(',')
			? $tag->parser->parseArguments()
			: new ArrayNode;
		$node->optional = str_ends_with($tag->name, '?');

		[$node->content] = yield;

		$node->init($tag);

		return $node;
	}


	public function print(PrintContext $context): string
	{
		return $context->format(
			<<<'XX'
				if ($ʟ_tmp = $this->global->assets->resolve(%node, %node, %dump)) %line {
					%node
				}
				XX,
			$this->name,
			$this->attributes,
			$this->optional,
			$this->position,
			$this->content,
		);
	}


	private function init(Tag $tag): void
	{
		$el = $tag->htmlElement;
		$tag->replaceNAttribute(new AuxiliaryNode(fn(PrintContext $context) => $context->format(
			<<<'XX'
				echo $this->global->assets->renderAttributes($ʟ_tmp, %dump, %dump);
				XX,
			strtolower($el->name),
			self::findUsedAttributes($el),
		)));
	}


	/** @internal */
	public static function findUsedAttributes(ElementNode $el): array
	{
		$res = [];
		foreach ($el->attributes?->children as $child) {
			if ($child instanceof AttributeNode && $child->name instanceof TextNode) {
				$res[$child->name->content] = NodeHelpers::toText($child->value) ?? true;
			}
		}
		return $res;
	}


	public function &getIterator(): \Generator
	{
		yield $this->name;
		yield $this->attributes;
		yield $this->content;
	}
}

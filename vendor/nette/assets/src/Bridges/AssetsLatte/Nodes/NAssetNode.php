<?php declare(strict_types=1);

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\AssetsLatte\Nodes;

use Latte\Compiler\{NodeHelpers, PrintContext, Tag};
use Latte\Compiler\Nodes\{AreaNode, AuxiliaryNode, StatementNode, TextNode};
use Latte\Compiler\Nodes\Html\{AttributeNode, ElementNode, ExpressionAttributeNode};
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;


/**
 * <img n:asset="reference">
 * <script n:asset="reference">
 * Fills in asset attributes (src, dimensions, etc.) on HTML elements.
 */
final class NAssetNode extends StatementNode
{
	public ExpressionNode $name;
	public ArrayNode $attributes;
	public AreaNode $content;
	public bool $optional;


	/** @return \Generator<int, ?list<string>, array{AreaNode, ?Tag}, static> */
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
				if ($ʟ_asset = $this->global->assets->resolve(%node, %node, %dump)) %line {
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
		assert($el !== null);
		$tag->replaceNAttribute(new AuxiliaryNode(fn(PrintContext $context) => $context->format(
			<<<'XX'
				echo $this->global->assets->renderAttributes($ʟ_asset, %dump, %dump);
				XX,
			strtolower($el->name),
			self::findUsedAttributes($el),
		)));
	}


	/**
	 * Collects attributes explicitly set on the HTML element, mapping name to value or true.
	 * @internal
	 * @return array<string, string|true>
	 */
	public static function findUsedAttributes(ElementNode $el): array
	{
		$res = [];
		foreach ($el->attributes?->children as $child) {
			if ($child instanceof AttributeNode && $child->name instanceof TextNode) {
				$res[$child->name->content] = NodeHelpers::toText($child->value) ?? true;
			} elseif ($child instanceof ExpressionAttributeNode) {
				$res[$child->name] = true;
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

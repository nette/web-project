<?php declare(strict_types=1);

namespace Nette\PHPStan\Database;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;


/**
 * Narrows return type of Selection::insert() (and GroupedSelection::insert(), which
 * inherits) to the concrete mapped EntityRow.
 *
 * insert() declares a wide union (the entity row plus schema-dependent fallbacks such
 * as null, an array or an affected-rows count) because its runtime return depends on
 * the table schema — whether the inserted row can be identified by a primary key —
 * which the argument shape alone cannot determine. The table-to-entity mapping carries
 * exactly that knowledge the library lacks: a mapped row class denotes an entity table
 * with a usable primary key, so a single-row insert returns that entity row. The
 * extension narrows only when
 *   - the argument is provably a string-keyed array (a single row, not a list/Selection), and
 *   - the Selection's row type T is a concrete EntityRow, not the bare ActiveRow.
 * The bare-ActiveRow case (unmapped, possibly keyless tables) keeps the honest union.
 */
class SelectionInsertReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	private const ActiveRowClass = ActiveRow::class;


	public function getClass(): string
	{
		return Selection::class;
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'insert';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		$args = $methodCall->getArgs();
		if ($args === []) {
			return null;
		}

		// Only a single associative-array row insert returns an entity row. A multi-row
		// insert (list with int keys, or a Selection object) returns an affected-rows
		// count, so we keep the declared union unless the argument is certainly a
		// string-keyed array.
		$argType = $scope->getType($args[0]->value);
		if (!$argType->isArray()->yes() || !$argType->getIterableKeyType()->isString()->yes()) {
			return null;
		}

		// Narrow only for a concrete mapped EntityRow; the bare ActiveRow (unmapped,
		// possibly keyless table) keeps the honest union.
		$rowType = $scope->getType($methodCall->var)
			->getTemplateType(Selection::class, 'T');
		$activeRow = new ObjectType(self::ActiveRowClass);
		$isStrictSubtype = $activeRow->isSuperTypeOf($rowType)->yes()
			&& !$rowType->isSuperTypeOf($activeRow)->yes();

		return $isStrictSubtype ? $rowType : null;
	}
}

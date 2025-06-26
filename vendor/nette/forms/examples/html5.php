<?php

/**
 * Nette Forms and HTML5.
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

use Nette\Forms\Form;
use Tracy\Debugger;
use Tracy\Dumper;

Debugger::enable();


$form = new Form;

$form->addGroup();

$form->addText('query', 'Search:')
	->setHtmlType('search')
	->setHtmlAttribute('autofocus');

$form->addInteger('count', 'Number of results:')
	->setDefaultValue(10)
	->addRule($form::Range, 'Must be in range from %d to %d', [1, 100]);

$form->addFloat('precision', 'Precision:')
	->setHtmlType('range')
	->setDefaultValue(50)
	->addRule($form::Range, 'Precision must be in range from %d to %d', [0, 100]);

$form->addEmail('email', 'Send to email:')
	->setHtmlAttribute('autocomplete', 'off')
	->setHtmlAttribute('placeholder', 'Optional, but Recommended');

$form->addSubmit('submit', 'Send');


if ($form->isSuccess()) {
	echo '<h2>Form was submitted and successfully validated</h2>';
	Dumper::dump($form->getValues());
	exit;
}


?>
<!DOCTYPE html>
<meta charset="utf-8">
<title>Nette Forms and HTML5</title>
<link rel="stylesheet" media="screen" href="assets/style.css" />
<script src="https://unpkg.com/nette-forms@3"></script>

<h1>Nette Forms and HTML5</h1>

<?php $form->render() ?>

<footer><a href="https://doc.nette.org/en/forms">see documentation</a></footer>

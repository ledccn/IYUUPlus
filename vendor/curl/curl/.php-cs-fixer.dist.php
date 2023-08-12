<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'visibility_required' => ['elements' => ['property', 'method']]
    ])
    ->setFinder($finder)
;   
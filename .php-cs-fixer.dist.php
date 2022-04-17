<?php

// See https://mlocati.github.io/php-cs-fixer-configurator/#version:3.8 for documentation

$finder = PhpCsFixer\Finder::create()
    ->exclude('node_modules')
    ->exclude('vendor')
    //->in(__DIR__);
    ->in(__DIR__ . '/src');

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PhpCsFixer'             => true, 
        '@PSR12'                  => true,
        '@PHP80Migration'         => true,
        'concat_space'            => ['spacing' => 'one'],
        'global_namespace_import' => true,
        'phpdoc_no_package'       => false,
        //'ternary_to_elvis_operator' => true,
        'use_arrow_functions'     => true,
        'yoda_style'              => [
            'always_move_variable' => true, 
            'equal' => true, 
            'identical' => true, 
            'less_and_greater' => true
        ],
    ])
    ->setFinder($finder);

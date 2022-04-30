<?php

// See https://mlocati.github.io/php-cs-fixer-configurator/#version:3.8 for documentation

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('node_modules')
    ->exclude('vendor')
    // for now don't look at build tools
    ->exclude('build-tools')
    // php-cs-fixer always corrupts this file, so ignore it
    ->notName('NestedTree.php');

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PhpCsFixer'             => true, 
        '@PSR12'                  => true,
        '@PHP80Migration'         => true,
        'binary_operator_spaces'  => ['default' => 'align'],
        'concat_space'            => ['spacing' => 'one'],
        'global_namespace_import' => true,
        'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
        'phpdoc_no_package'       => false,
        'phpdoc_var_without_name' => false,
        //'psr_autoloading' => ['dir' => './src'],
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

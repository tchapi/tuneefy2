<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->exclude(['_support', '_data', '_output'])
; 

return (new PhpCsFixer\Config())
    ->setRules(array(
        '@Symfony' => true,
        'ordered_imports' => true,                      // Order "use" alphabetically
        'array_syntax' => ['syntax' => 'short'],        // Replace array() by []
        'no_useless_return' => true,                    // Keep return null;
        'phpdoc_order' => true,                         // Clean up the /** php doc */
        'linebreak_after_opening_tag' => true,
        'multiline_whitespace_before_semicolons' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
    ))
    ->setUsingCache(false)
    ->setFinder($finder)
;

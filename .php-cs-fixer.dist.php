<?php

/**
 * PHP CS Fixer — Beplus Manager (WordPress-friendly defaults).
 *
 * @package BeplusManager
 */

$root = __DIR__;

$finder = PhpCsFixer\Finder::create()
	->in(
		array(
			$root . '/src',
			$root . '/includes',
		),
	)
	->append( array( $root . '/beplus-manager.php' ) )
	->name( '*.php' )
	->ignoreDotFiles( true )
	->ignoreVCS( true );

return ( new PhpCsFixer\Config() )
	->setRiskyAllowed( true )
	->setIndent( '	' )
	->setLineEnding( "\n" )
	->setFinder( $finder )
	->setRules(
		array(
			'array_syntax'                  => array( 'syntax' => 'long' ),
			'blank_line_after_opening_tag'  => true,
			'blank_line_between_import_groups' => true,
			'concat_space'                  => array( 'spacing' => 'one' ),
			'fully_qualified_strict_types'  => true,
			'linebreak_after_opening_tag'   => true,
			'method_argument_space'         => array(
				'on_multiline'                     => 'ensure_fully_multiline',
				'keep_multiple_spaces_after_comma' => true,
			),
			'no_extra_blank_lines'          => array(
				'tokens' => array(
					'extra',
					'throw',
					'use',
				),
			),
			'no_trailing_whitespace'        => true,
			'no_trailing_whitespace_in_comment' => true,
			'no_unused_imports'             => true,
			'ordered_imports'               => array( 'sort_algorithm' => 'alpha' ),
			'phpdoc_align'                  => array( 'align' => 'vertical' ),
			'phpdoc_order'                  => true,
			'phpdoc_separation'             => true,
			'phpdoc_summary'                => true,
			'phpdoc_trim'                   => true,
			'single_blank_line_at_eof'      => true,
			'single_quote'                  => true,
			'spaces_inside_parentheses'     => array( 'space' => 'single' ),
			'trailing_comma_in_multiline'   => array(
				'elements' => array( 'arrays' ),
			),
		),
	);

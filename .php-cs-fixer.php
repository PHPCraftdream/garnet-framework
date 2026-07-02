<?php
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
declare(strict_types=1);

$config = new PhpCsFixer\Config();
$rules = [
    'ordered_imports' => ['sort_algorithm' => 'alpha'],

    'array_indentation' => true,
    'array_syntax' => true,
    'assign_null_coalescing_to_coalesce_equal' => true,
    'binary_operator_spaces' => true,
    'blank_line_before_statement' => [
        'statements' => [
            "break",
            "case",
            "continue",
            "declare",
            "default",
            "do",
            "exit",
            "for",
            "foreach",
            "goto",
            "if",
            "return",
            "switch",
            "throw",
            "try",
            "while",
            "yield",
            "yield_from"
        ]
    ],
    'blank_line_between_import_groups' => true,
    'braces' => [
        'allow_single_line_anonymous_class_with_empty_body' => true,
        'allow_single_line_closure' => true,
        'position_after_anonymous_constructs' => 'same',
        'position_after_control_structures' => 'same',
        'position_after_functions_and_oop_constructs' => 'same',
    ],
    'cast_spaces' => ['space' => 'none'],
    'class_attributes_separation' => ['elements' =>
        ['const' => 'one', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none', 'case' => 'none']
    ],
    'class_reference_name_casing' => true,
    'combine_consecutive_issets' => true,
    'combine_consecutive_unsets' => true,
    'compact_nullable_typehint' => true,
    'concat_space' => ['spacing' => 'one'],
    'constant_case' => ['case' => 'lower'],
    'control_structure_braces' => true,
    'control_structure_continuation_position' => ['position' => 'same_line'],
    'curly_braces_position' => [
        'allow_single_line_anonymous_functions' => true,
        'allow_single_line_empty_anonymous_classes' => true,
        'anonymous_classes_opening_brace' => 'same_line',
        'anonymous_functions_opening_brace' => 'same_line',
        'classes_opening_brace' => 'same_line',
        'control_structures_opening_brace' => 'same_line',
        'functions_opening_brace' => 'same_line',
    ],
    'declare_equal_normalize' => ['space' => 'none'],
    'declare_parentheses' => true,
    'declare_strict_types' => true,
    'elseif' => true,
    'explicit_indirect_variable' => true,
    'explicit_string_variable' => true,
    'full_opening_tag' => true,
    'fully_qualified_strict_types' => true,
    'function_declaration' => ['closure_function_spacing' => 'one'],
    'function_typehint_space' => true,
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
    'include' => true,
    'indentation_type' => true,
    'integer_literal_case' => true,
    'is_null' => true,
    'lambda_not_used_import' => true,
    'line_ending' => true,
    'linebreak_after_opening_tag' => false,
    'list_syntax' => ['syntax' => 'short'],
    'lowercase_cast' => true,
    'lowercase_keywords' => true,
    'lowercase_static_reference' => true,
    'magic_constant_casing' => true,
    'magic_method_casing' => true,
    'native_function_casing' => true,
    'native_function_type_declaration_casing' => true,
    'new_with_braces' => true,
    'no_closing_tag' => true,
    'no_extra_blank_lines' => [
        'tokens' => [
            "attribute",
            "break",
            "case",
            "continue",
            "curly_brace_block",
            "default",
            "extra",
            "parenthesis_brace_block",
            "return",
            "square_brace_block",
            "switch",
            "throw",
            "use",
            "use_trait"
        ]
    ],
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_multiple_statements_per_line' => true,
    'no_spaces_after_function_name' => true,
    'no_spaces_around_offset' => true,
    'no_spaces_inside_parenthesis' => true,
    'no_trailing_comma_in_list_call' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'no_trailing_comma_in_singleline_function_call' => true,
    'no_trailing_whitespace' => true,
    'no_unneeded_import_alias' => true,
    'no_unused_imports' => true,
    'no_useless_else' => true,
    'no_useless_nullsafe_operator' => true,
    'no_whitespace_in_blank_line' => true,
    'object_operator_without_whitespace' => true,
    'octal_notation' => true,
    'phpdoc_add_missing_param_annotation' => true,
    'return_type_declaration' => ['space_before' => 'none'],
    'short_scalar_cast' => true,
    'simple_to_complex_string_variable' => true,
    'single_blank_line_at_eof' => true,
    'single_blank_line_before_namespace' => true,
    'single_line_after_imports' => true,
    'single_line_comment_spacing' => true,
    'single_quote' => true,
    'single_space_after_construct' => true,
    'space_after_semicolon' => true,
    'statement_indentation' => true,
    // 'static_lambda' => true,
    'strict_comparison' => true,
    'strict_param' => true,
    'string_length_to_empty' => true,
    'ternary_operator_spaces' => true,
    'ternary_to_null_coalescing' => true,
    'trim_array_spaces' => true,
    'types_spaces' => ['space' => 'none', 'space_multiple_catch' => 'none'],
    'use_arrow_functions' => true,
    'visibility_required' => true,
    'void_return' => true,
    'blank_line_after_namespace' => true,
];

$d = DIRECTORY_SEPARATOR;

// Optionally include the sibling Apps/Application template — only when
// Framework lives inside the monorepo. In a standalone published package
// that path doesn't exist; skip it without erroring.
$finderPaths = [__DIR__];
$applicationTemplate = join($d, [__DIR__, '..', 'Apps', 'Application']);
if (is_dir($applicationTemplate)) {
    $finderPaths[] = $applicationTemplate;
}

$finder = PhpCsFixer\Finder::create()
    ->in($finderPaths)
    // *Gen.php are gitignored build artifacts emitted by the rspack
    // PhpClassGeneratorPlugin (Framework{Js,Css,Assets}Gen, <App>{Js,Css}Gen).
    // They're regenerated on every `garnet build`, so linting them makes
    // cs:check flaky — green on a clean tree, red right after a build.
    ->notName('*Gen.php');

return $config->setRules($rules)->setFinder($finder);

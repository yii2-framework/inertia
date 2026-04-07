<?php

declare(strict_types=1);

return [
    'file_get_contents' => [
        'signatureArguments' => 'string $filename, bool $use_include_path = false, mixed $context = null, int $offset = 0, ?int $length = null',
        'arguments' => '$filename, $use_include_path, $context, $offset, $length',
    ],
    'trim' => [
        'signatureArguments' => 'string $string, string $characters = " \\n\\r\\t\\v\\0"',
        'arguments' => '$string, $characters',
    ],
    'unserialize' => [
        'signatureArguments' => 'string $data, array $options = []',
        'arguments' => '$data, $options',
    ],
];

<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Returns the MIME type map for OpenAI supported files.
 * This file should `return` the array.
 */
return [
    'c' => 'text/x-c',
    'cpp' => 'text/x-c++',
    'cs' => 'text/x-csharp',
    'css' => 'text/css',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'go' => 'text/x-golang',
    'html' => 'text/html',
    'java' => 'text/x-java',
    'js' => 'text/javascript',
    'json' => 'application/json',
    'md' => 'text/markdown',
    'pdf' => 'application/pdf',
    'php' => 'text/x-php',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'py' => 'text/x-python',
    'rb' => 'text/x-ruby',
    'sh' => 'application/x-sh',
    'tex' => 'text/x-tex',
    'ts' => 'application/typescript',
    'txt' => 'text/plain',
    'log' => 'text/plain',
    'csv' => 'text/csv',
    'xml' => 'application/xml',
    'rtf' => 'application/rtf',
];
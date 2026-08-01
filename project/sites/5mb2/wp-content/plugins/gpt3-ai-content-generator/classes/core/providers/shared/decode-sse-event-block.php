<?php

namespace WPAICG\Core\Providers\Shared;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decodes provider SSE blocks that use optional event fields and JSON data lines.
 *
 * @return array<string, mixed>|null
 */
function decode_event_type_sse_event_block(string $event_block): ?array
{
    $event_type = null;
    $event_data_lines = [];

    foreach (preg_split("/\r?\n/", $event_block) as $line) {
        $line = rtrim((string) $line, "\r");

        if ($line === '' || $line[0] === ':' || strpos($line, ':') === false) {
            continue;
        }

        [$field, $value] = explode(':', $line, 2);
        $field = trim($field);
        $value = ltrim((string) $value, ' ');

        if ($field === 'event') {
            $event_type = trim($value);
        } elseif ($field === 'data') {
            $event_data_lines[] = $value;
        }
    }

    if (empty($event_data_lines)) {
        return null;
    }

    $event_data = implode("\n", $event_data_lines);
    if ($event_data === '[DONE]') {
        return [
            'event' => '[DONE]',
            'payload' => null,
        ];
    }

    $decoded_data = json_decode($event_data, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_data)) {
        return null;
    }

    if (($event_type === null || $event_type === '') && isset($decoded_data['type']) && is_string($decoded_data['type'])) {
        $event_type = $decoded_data['type'];
    }

    return [
        'event' => ($event_type === null || $event_type === '') ? 'message' : $event_type,
        'payload' => $decoded_data,
    ];
}

/**
 * Decodes provider SSE blocks that only consume JSON data lines.
 *
 * @return array<string, mixed>|null
 */
function decode_data_only_sse_event_block(string $event_block): ?array
{
    $event_data_lines = [];

    foreach (preg_split("/\r?\n/", $event_block) as $line) {
        $line = rtrim((string) $line, "\r");

        if ($line === '' || $line[0] === ':' || strpos($line, ':') === false) {
            continue;
        }

        [$field, $value] = explode(':', $line, 2);
        if (trim($field) === 'data') {
            $event_data_lines[] = ltrim((string) $value, ' ');
        }
    }

    if (empty($event_data_lines)) {
        return null;
    }

    $event_data = trim(implode("\n", $event_data_lines));
    if ($event_data === '[DONE]') {
        return [
            'kind' => 'done',
            'payload' => null,
        ];
    }

    $decoded_data = json_decode($event_data, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_data)) {
        return null;
    }

    return [
        'kind' => 'payload',
        'payload' => $decoded_data,
    ];
}

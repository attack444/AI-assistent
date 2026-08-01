<?php

namespace WPAICG\Core\Providers\Shared;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract complete SSE event blocks from the buffer while leaving any partial tail intact.
 *
 * @return array<int, string>
 */
function extract_sse_event_blocks(string &$current_buffer): array
{
    $event_blocks = [];

    while (preg_match("/\r?\n\r?\n/", $current_buffer, $separator_match, PREG_OFFSET_CAPTURE) === 1) {
        $separator_offset = (int) $separator_match[0][1];
        $separator_length = strlen((string) $separator_match[0][0]);
        $event_block = (string) substr($current_buffer, 0, $separator_offset);
        $current_buffer = (string) substr($current_buffer, $separator_offset + $separator_length);

        if (trim($event_block) !== '') {
            $event_blocks[] = $event_block;
        }
    }

    return $event_blocks;
}

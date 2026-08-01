<?php

/**
 * Defines the localized text strings used in the AIPKit Dashboard JavaScript.
 * Returns an array of strings, keyed by their identifier.
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

return [
    // General / Loader
    /* translators: %s is the name of the module being loaded */
    'loading'                   => __('Loading %s...', 'gpt3-ai-content-generator'),
    'errorLoadingModuleTitle'   => __('Error Loading Module', 'gpt3-ai-content-generator'),
    /* translators: %s is the name of the module that failed to load */
    'errorLoadingModuleMsg'     => __('An error occurred while loading the \'%s\' module. Please try again later or check the browser console for details.', 'gpt3-ai-content-generator'),
    'errorDetails'              => __('Details:', 'gpt3-ai-content-generator'),
    'noKnowledgeBasesFound' => __('No knowledge bases found. Click "Add Content" to create one.', 'gpt3-ai-content-generator'),
    // Chat Preview Placeholders
    'previewPlaceholderSelect'  => __('Select a bot to see the preview.', 'gpt3-ai-content-generator'),
    'previewPlaceholderCreate'  => __('Configure the new bot and save it to see the preview.', 'gpt3-ai-content-generator'),
    'previewPlaceholderSettings' => __('Preview is not applicable for Chat Settings.', 'gpt3-ai-content-generator'),
    'previewPlaceholderLogs'    => __('Preview is not applicable for Chat Logs.', 'gpt3-ai-content-generator'),
    'noBotsPlaceholder'         => __('No chatbots found. Create one to get started!', 'gpt3-ai-content-generator'),
    // Logs Viewer
    'logViewTitle'              => __('Chat Log Details', 'gpt3-ai-content-generator'),
    'logLoading'                => __('Loading log details...', 'gpt3-ai-content-generator'),
    'logErrorLoading'           => __('Error loading log details.', 'gpt3-ai-content-generator'),
    'logBotLabel'               => __('Bot', 'gpt3-ai-content-generator'),
    'logUserLabel'              => __('User', 'gpt3-ai-content-generator'),
    'logSessionLabel'           => __('Session ID', 'gpt3-ai-content-generator'),
    'logStartLabel'             => __('Started', 'gpt3-ai-content-generator'),
    'logLastActivityLabel'      => __('Last Activity', 'gpt3-ai-content-generator'),
    'logProviderLabel'          => __('Provider', 'gpt3-ai-content-generator'),
    'logModelLabel'             => __('Model', 'gpt3-ai-content-generator'),
    'logIpLabel'                => __('IP Address', 'gpt3-ai-content-generator'),
    'noLogsFound'               => __('No logs found matching your criteria.', 'gpt3-ai-content-generator'),
    'logExportFilteredConfirmMsg' => __('Export all messages matching current filters?', 'gpt3-ai-content-generator'),
    'logExportAllConfirmMsg'    => __('Export all messages? This might take a while for large datasets.', 'gpt3-ai-content-generator'),
    'logExportStarting'         => __('Starting...', 'gpt3-ai-content-generator'),
    /* translators: %1$d is the current conversation number, %2$d is the total number of conversations */
    'logExportProgress' 		=> __('Exporting conversation %1$d / %2$d...', 'gpt3-ai-content-generator'),
    'logExportComplete'         => __('Done!', 'gpt3-ai-content-generator'),
    'logExportError'            => __('Export failed:', 'gpt3-ai-content-generator'),
    'logExportNoLogs'           => __('No messages found to export.', 'gpt3-ai-content-generator'),
    'logDeleteFilteredConfirmMsg' => __('Delete all conversations matching current filters? This action cannot be undone.', 'gpt3-ai-content-generator'),
    'logDeleteAllConfirmMsg'    => __('Delete ALL conversations? This will remove ALL chat history and cannot be undone.', 'gpt3-ai-content-generator'),
    'logDeleteStarting'         => __('Starting...', 'gpt3-ai-content-generator'),
    /* translators: %1$d is the current conversation number, %2$d is the total number of conversations */
    'logDeleteProgress' 		=> __('Deleting conversation %1$d / %2$d...', 'gpt3-ai-content-generator'),
    'logDeleteComplete'         => __('Done!', 'gpt3-ai-content-generator'),
    'logDeleteError'            => __('Deletion failed:', 'gpt3-ai-content-generator'),
    'logDeleteNoLogs'           => __('No conversations found to delete.', 'gpt3-ai-content-generator'),
    'confirm'                   => __('Confirm', 'gpt3-ai-content-generator'),
    'confirmDeleteFiltered'     => __('Confirm Delete Filtered', 'gpt3-ai-content-generator'),
    'confirmDeleteAll'          => __('Confirm Delete ALL', 'gpt3-ai-content-generator'),
    'cancel'                    => __('Cancel', 'gpt3-ai-content-generator'),
    // Settings Chart
    'chartLoading'              => __('Loading chart data...', 'gpt3-ai-content-generator'),
    'chartError'                => __('Error loading chart data.', 'gpt3-ai-content-generator'),
    'chartNoData'               => __('No token usage data found for the selected period.', 'gpt3-ai-content-generator'),
    // Frontend Chat UI (subset needed for preview)
    'sendMessage'               => __('Send Message', 'gpt3-ai-content-generator'),
    'sending'                   => __('Sending...', 'gpt3-ai-content-generator'),
    'typeMessage'               => __('Type your message...', 'gpt3-ai-content-generator'),
    'thinking'                  => __('Thinking', 'gpt3-ai-content-generator'),
    'streaming'                 => __('Streaming...', 'gpt3-ai-content-generator'),
    'errorPrefix'               => __('Error:', 'gpt3-ai-content-generator'),
    'userPrefix'                => __('User', 'gpt3-ai-content-generator'),
    'clearChat'                 => __('Clear Chat', 'gpt3-ai-content-generator'),
    'fullscreen'                => __('Fullscreen', 'gpt3-ai-content-generator'),
    'exitFullscreen'            => __('Exit Fullscreen', 'gpt3-ai-content-generator'),
    'fullscreenError'           => __('Error: Fullscreen functionality is unavailable.', 'gpt3-ai-content-generator'),
    'download'                  => __('Download Transcript', 'gpt3-ai-content-generator'),
    'downloadTxt'               => __('Download TXT', 'gpt3-ai-content-generator'),
    'downloadPdf'               => __('Download PDF', 'gpt3-ai-content-generator'),
    'downloadEmpty'             => __('Nothing to download.', 'gpt3-ai-content-generator'),
    'pdfError'                  => __('Could not open the print window. Please allow popups and try again.', 'gpt3-ai-content-generator'),
    'streamError'               => __('Stream error. Please try again.', 'gpt3-ai-content-generator'),
    'connError'                 => __('Connection error. Please try again.', 'gpt3-ai-content-generator'),
    'copyActionLabel'           => __('Copy response', 'gpt3-ai-content-generator'),
    'copySuccess'               => __('Copied!', 'gpt3-ai-content-generator'),
    'copyFail'                  => __('Failed to copy', 'gpt3-ai-content-generator'),
    'feedbackLikeLabel'         => __('Like response', 'gpt3-ai-content-generator'),
    'feedbackDislikeLabel'      => __('Dislike response', 'gpt3-ai-content-generator'),
    'feedbackSubmitted'         => __('Feedback submitted', 'gpt3-ai-content-generator'),
    'feedbackError'             => __('Error saving feedback', 'gpt3-ai-content-generator'),
    'sidebarToggle'             => __('Toggle Conversation Sidebar', 'gpt3-ai-content-generator'),
    'newChat'                   => __('New Chat', 'gpt3-ai-content-generator'),
    'conversations'             => __('Conversations', 'gpt3-ai-content-generator'),
    'configure'                 => __('Configure', 'gpt3-ai-content-generator'),
    'enableToConfigure'         => __('Enable to configure', 'gpt3-ai-content-generator'),
    'historyGuests'             => __('History unavailable for guests.', 'gpt3-ai-content-generator'),
    'historyEmpty'              => __('No past conversations.', 'gpt3-ai-content-generator'),
    'playActionLabel'           => __('Play audio', 'gpt3-ai-content-generator'),
    'pauseActionLabel'          => __('Pause audio', 'gpt3-ai-content-generator'),
    'reasoningLabel'            => __('Reasoning', 'gpt3-ai-content-generator'),
    'thinkingLabel'             => __('Thinking', 'gpt3-ai-content-generator'),
    'offLabel'                  => __('off', 'gpt3-ai-content-generator'),
    'onLabel'                   => __('on', 'gpt3-ai-content-generator'),
    'reasoningHelpGeneric'      => '',
    'reasoningHelpOpenAI'       => '',
    'reasoningHelpOllama'       => __('Maps to Ollama think mode. GPT-OSS uses levels, while most other thinking-capable Ollama models use on or off.', 'gpt3-ai-content-generator'),
    'openaiOnly'                => __('Available for OpenAI only', 'gpt3-ai-content-generator'),
    'openaiReasoningOnly'       => __('Available for OpenAI reasoning models only', 'gpt3-ai-content-generator'),
    'openaiOllamaReasoningOnly' => __('Available for OpenAI reasoning models and Ollama thinking-capable models', 'gpt3-ai-content-generator'),
    'openaiClaudeOnly'          => __('Available for OpenAI, Anthropic, OpenRouter, and xAI only', 'gpt3-ai-content-generator'),
    // User Credits
    'userCreditsLoading'        => __('Loading user credits...', 'gpt3-ai-content-generator'),
    'userCreditsError'          => __('Error loading user credits.', 'gpt3-ai-content-generator'),
    'userCreditsNoUsers'        => __('No user token data found.', 'gpt3-ai-content-generator'),
    'userCreditsEdit'           => __('Edit Credits', 'gpt3-ai-content-generator'),
    'userCreditsReset'          => __('Reset Period', 'gpt3-ai-content-generator'),
    // Vector Store Specific
    'vectorSearching'           => __('Searching vector store...', 'gpt3-ai-content-generator'),
];

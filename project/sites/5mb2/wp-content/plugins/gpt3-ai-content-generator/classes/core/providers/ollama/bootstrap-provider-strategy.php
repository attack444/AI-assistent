<?php

namespace WPAICG\Core\Providers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Ensure Interface and Base are loaded (normally loaded by Provider_Dependencies_Loader)
if (!interface_exists(ProviderStrategyInterface::class)) {
    $base_path = __DIR__ . '/../';
    if (file_exists($base_path . 'interface-provider-strategy.php')) require_once $base_path . 'interface-provider-strategy.php';
}
if (!class_exists(BaseProviderStrategy::class)) {
    $base_path = __DIR__ . '/../';
    if (file_exists($base_path . 'base-provider-strategy.php')) require_once $base_path . 'base-provider-strategy.php';
}

// Load traits required by lib strategy
$traits_path = __DIR__ . '/../traits/';
if (file_exists($traits_path . 'trait-aipkit-chat-completions-payload.php')) require_once $traits_path . 'trait-aipkit-chat-completions-payload.php';

// Include the actual strategy implementation from lib (Pro addon area)
$lib_strategy = defined('WPAICG_PLUGIN_DIR') ? WPAICG_PLUGIN_DIR . 'lib/addons/ollama/provider-strategy.php' : null;
if ($lib_strategy && file_exists($lib_strategy)) {
    require_once $lib_strategy;
}

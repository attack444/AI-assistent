<?php

namespace WPAICG\Chat\Admin\Ajax;

use WPAICG\Chat\Admin\Ajax\Traits\Trait_CheckModuleAccess;
use WPAICG\Chat\Admin\Ajax\Traits\Trait_CheckFrontendPermissions;
use WPAICG\Chat\Admin\Ajax\Traits\Trait_SendWPError;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Base class for Chat Admin AJAX Handlers.
 * Provides common access checks and error handling by using traits.
 */
abstract class BaseAjaxHandler {

    use Trait_CheckModuleAccess;
    use Trait_CheckFrontendPermissions;
    use Trait_SendWPError;

    protected $required_capability = 'aipkit_manage_settings'; // Default capability
}

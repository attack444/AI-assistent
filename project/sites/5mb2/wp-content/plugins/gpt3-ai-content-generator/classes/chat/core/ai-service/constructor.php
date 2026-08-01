<?php

namespace WPAICG\Chat\Core\AIService;

use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
// Corrected namespaces for PostProcessors
use WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor;
use WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for the AIService constructor.
 * Initializes dependencies.
 *
 * @param \WPAICG\Chat\Core\AIService $serviceInstance The instance of the AIService class.
 * @return void
 */
function constructor(\WPAICG\Chat\Core\AIService $serviceInstance): void {
    if (!class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
        // Set properties to null or handle error appropriately
        $serviceInstance->set_ai_caller(null);
        $serviceInstance->set_log_storage(null);
        $serviceInstance->set_vector_store_manager(null);
        $serviceInstance->set_pinecone_post_processor(null);
        $serviceInstance->set_qdrant_post_processor(null);
        return;
    }
    $serviceInstance->set_ai_caller(new AIPKit_AI_Caller());

    if (!class_exists(\WPAICG\Chat\Storage\LogStorage::class)) {
         $serviceInstance->set_log_storage(null);
    } else {
        $serviceInstance->set_log_storage(new LogStorage());
    }

    if (!class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) {
        // Vector_Store_Manager is loaded by DependencyLoader, so a require_once here might be redundant
        // but we check to ensure it's available.
        $manager_path = WPAICG_PLUGIN_DIR . 'classes/vector/class-aipkit-vector-store-manager.php';
        if (file_exists($manager_path) && !class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) { // Check if not already loaded
            require_once $manager_path;
        }
    }
    if (class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) {
        $serviceInstance->set_vector_store_manager(new \WPAICG\Vector\AIPKit_Vector_Store_Manager());
    } else {
        $serviceInstance->set_vector_store_manager(null);
    }

    // Pinecone Post Processor
    if (class_exists(\WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor::class)) {
        $serviceInstance->set_pinecone_post_processor(new \WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor());
    } else {
        $serviceInstance->set_pinecone_post_processor(null);
    }

    // Qdrant Post Processor
    if (class_exists(\WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor::class)) {
        $serviceInstance->set_qdrant_post_processor(new \WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor());
    } else {
         $serviceInstance->set_qdrant_post_processor(null);
    }
}
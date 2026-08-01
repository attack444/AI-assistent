<?php

// Created to fetch user's token purchase history from WooCommerce orders

namespace WPAICG\Shortcodes\TokenUsage\Data;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Fetch token purchase history for a specific user.
 * 
 * @param int $user_id The user ID.
 * @param int $limit Maximum number of purchases to return. Default: 10.
 * @return array Array of purchase details with order info, tokens granted, date, etc.
 */
function get_user_purchase_history_logic(int $user_id, int $limit = 10): array
{
    // Return empty array if WooCommerce is not active
    if (!function_exists('wc_get_orders')) {
        return [];
    }

    try {
        // Get completed orders for this user
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => 'completed',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $purchase_history = [];

        foreach ($orders as $order) {
            $order_data = [
                'order_id' => $order->get_id(),
                'date' => $order->get_date_completed(),
                'total_amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'tokens_granted' => 0,
                'products' => [],
            ];

            $has_token_products = false;

            // Check each item in the order for token packages
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $is_token_package = get_post_meta($product_id, '_aipkit_is_token_package', true);

                if ($is_token_package === 'yes') {
                    $has_token_products = true;
                    $tokens_per_product = (int) get_post_meta($product_id, '_aipkit_tokens_amount', true);
                    $quantity = $item->get_quantity();
                    $product_tokens = $tokens_per_product * $quantity;
                    
                    $order_data['tokens_granted'] += $product_tokens;
                    $order_data['products'][] = [
                        'name' => $item->get_name(),
                        'quantity' => $quantity,
                        'tokens_per_item' => $tokens_per_product,
                        'total_tokens' => $product_tokens,
                        'line_total' => $item->get_total(),
                    ];
                }
            }

            // Only include orders that contained token packages
            if ($has_token_products) {
                $purchase_history[] = $order_data;
            }
        }

        return $purchase_history;

    } catch (Exception $e) {
        return [];
    }
}

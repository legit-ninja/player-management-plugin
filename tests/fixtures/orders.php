<?php
/**
 * Sample WooCommerce order data for testing
 */

return [
    'completed_order' => [
        'order_id' => 100,
        'customer_id' => 1,
        'status' => 'completed',
        'items' => [
            [
                'item_id' => 1,
                'product_id' => 50,
                'variation_id' => 51,
                'quantity' => 1,
                'meta' => [
                    'Assigned Attendee' => 'John Doe',
                    'intersoccer_player_index' => 0,
                    'activity_type' => 'Camp',
                ],
            ],
        ],
    ],
    
    'processing_order' => [
        'order_id' => 101,
        'customer_id' => 1,
        'status' => 'processing',
        'items' => [
            [
                'item_id' => 2,
                'product_id' => 52,
                'variation_id' => 53,
                'quantity' => 1,
                'meta' => [
                    'Assigned Attendee' => 'Jane Smith',
                    'intersoccer_player_index' => 1,
                    'activity_type' => 'Course',
                ],
            ],
        ],
    ],
    
    'order_multiple_items' => [
        'order_id' => 102,
        'customer_id' => 2,
        'status' => 'completed',
        'items' => [
            [
                'item_id' => 3,
                'product_id' => 50,
                'variation_id' => 51,
                'quantity' => 1,
                'meta' => [
                    'Assigned Attendee' => 'Alice Johnson',
                    'intersoccer_player_index' => 0,
                    'activity_type' => 'Camp',
                ],
            ],
            [
                'item_id' => 4,
                'product_id' => 52,
                'variation_id' => 53,
                'quantity' => 1,
                'meta' => [
                    'Assigned Attendee' => 'Bob Wilson',
                    'intersoccer_player_index' => 1,
                    'activity_type' => 'Camp',
                ],
            ],
        ],
    ],
    
    'pending_order' => [
        'order_id' => 103,
        'customer_id' => 1,
        'status' => 'pending',
        'items' => [
            [
                'item_id' => 5,
                'product_id' => 54,
                'variation_id' => 55,
                'quantity' => 1,
                'meta' => [
                    'Assigned Attendee' => 'Charlie Brown',
                    'intersoccer_player_index' => 2,
                    'activity_type' => 'Birthday',
                ],
            ],
        ],
    ],
];


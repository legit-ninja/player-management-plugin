<?php
/**
 * Sample plugin settings for testing
 */

return [
    'default_settings' => [
        'min_age' => 3,
        'max_age' => 18,
        'enable_avs_validation' => true,
        'require_medical_info' => false,
        'cache_duration' => 1800, // 30 minutes
    ],
    
    'strict_settings' => [
        'min_age' => 5,
        'max_age' => 16,
        'enable_avs_validation' => true,
        'require_medical_info' => true,
        'cache_duration' => 3600, // 1 hour
    ],
    
    'relaxed_settings' => [
        'min_age' => 3,
        'max_age' => 21,
        'enable_avs_validation' => false,
        'require_medical_info' => false,
        'cache_duration' => 600, // 10 minutes
    ],
];


<?php
/**
 * Sample player data for testing
 */

return [
    'valid_player' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'dob' => '2015-05-15',
        'gender' => 'male',
        'avs_number' => '756.1234.5678.90',
        'medical_conditions' => '',
        'creation_timestamp' => 1625155200,
    ],
    
    'valid_player_female' => [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'dob' => '2014-08-20',
        'gender' => 'female',
        'avs_number' => '756.9876.5432.10',
        'medical_conditions' => 'Allergies to peanuts',
        'creation_timestamp' => 1625155200,
    ],
    
    'player_with_unicode' => [
        'first_name' => 'François',
        'last_name' => 'Müller',
        'dob' => '2016-03-10',
        'gender' => 'male',
        'avs_number' => '756.1111.2222.33',
        'medical_conditions' => '',
        'creation_timestamp' => 1625155200,
    ],
    
    'player_too_young' => [
        'first_name' => 'Baby',
        'last_name' => 'Test',
        'dob' => date('Y-m-d', strtotime('-2 years')),
        'gender' => 'other',
        'avs_number' => '',
        'medical_conditions' => '',
        'creation_timestamp' => time(),
    ],
    
    'player_too_old' => [
        'first_name' => 'Teen',
        'last_name' => 'Test',
        'dob' => date('Y-m-d', strtotime('-14 years')),
        'gender' => 'male',
        'avs_number' => '',
        'medical_conditions' => '',
        'creation_timestamp' => time(),
    ],
    
    'player_missing_first_name' => [
        'first_name' => '',
        'last_name' => 'Doe',
        'dob' => '2015-05-15',
        'gender' => 'male',
        'avs_number' => '',
        'medical_conditions' => '',
        'creation_timestamp' => time(),
    ],
    
    'player_invalid_gender' => [
        'first_name' => 'Test',
        'last_name' => 'User',
        'dob' => '2015-05-15',
        'gender' => 'invalid',
        'avs_number' => '',
        'medical_conditions' => '',
        'creation_timestamp' => time(),
    ],
    
    'multiple_players' => [
        [
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'dob' => '2014-01-15',
            'gender' => 'female',
            'avs_number' => '756.1111.1111.11',
            'medical_conditions' => '',
            'creation_timestamp' => 1625155200,
        ],
        [
            'first_name' => 'Bob',
            'last_name' => 'Wilson',
            'dob' => '2015-06-20',
            'gender' => 'male',
            'avs_number' => '756.2222.2222.22',
            'medical_conditions' => 'Asthma',
            'creation_timestamp' => 1625155200,
        ],
        [
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'dob' => '2016-12-05',
            'gender' => 'male',
            'avs_number' => '756.3333.3333.33',
            'medical_conditions' => '',
            'creation_timestamp' => 1625155200,
        ],
    ],
];


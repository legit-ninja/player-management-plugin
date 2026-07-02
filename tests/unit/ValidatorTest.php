<?php
/**
 * Tests for InterSoccer_Player_Validator class
 * Target: 100% coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/class-validator.php';

class ValidatorTest extends InterSoccer_Test_Case
{
    private $validator;
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InterSoccer_Player_Validator();
        $this->fixtures = require __DIR__ . '/../fixtures/players.php';
        
        // Mock WordPress translation functions
        WP_Mock::userFunction('__')->andReturnUsing(function($text) {
            return $text;
        });
    }

    /**
     * Test validate_player_data with valid data
     */
    public function test_validate_player_data_with_valid_data()
    {
        $result = $this->validator->validate_player_data($this->fixtures['valid_player']);
        $this->assertTrue($result, 'Valid player data should pass validation');
    }

    /**
     * Test validate_player_data with valid female player
     */
    public function test_validate_player_data_with_female_player()
    {
        $result = $this->validator->validate_player_data($this->fixtures['valid_player_female']);
        $this->assertTrue($result);
    }

    /**
     * Test validate_player_data with unicode characters
     */
    public function test_validate_player_data_with_unicode_names()
    {
        $result = $this->validator->validate_player_data($this->fixtures['player_with_unicode']);
        $this->assertTrue($result, 'Unicode characters in names should be accepted');
    }

    /**
     * Test validate_player_data with missing first name
     */
    public function test_validate_player_data_missing_first_name()
    {
        $result = $this->validator->validate_player_data($this->fixtures['player_missing_first_name']);
        $this->assertFalse($result, 'Missing first name should fail validation');
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('first_name', $errors);
    }

    /**
     * Test validate_player_data with missing last name
     */
    public function test_validate_player_data_missing_last_name()
    {
        $data = $this->fixtures['valid_player'];
        $data['last_name'] = '';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('last_name', $errors);
    }

    /**
     * Test validate_player_data with invalid first name characters
     */
    public function test_validate_player_data_invalid_first_name_characters()
    {
        $data = $this->fixtures['valid_player'];
        $data['first_name'] = 'John123';  // Numbers not allowed
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('first_name', $errors);
    }

    /**
     * Test validate_player_data with name too long
     */
    public function test_validate_player_data_name_too_long()
    {
        $data = $this->fixtures['valid_player'];
        $data['first_name'] = str_repeat('a', 51);  // 51 characters, max is 50
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
    }

    /**
     * Test validate_player_data with missing date of birth
     */
    public function test_validate_player_data_missing_dob()
    {
        $data = $this->fixtures['valid_player'];
        $data['dob'] = '';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('dob', $errors);
    }

    /**
     * Test validate_player_data with invalid date format
     */
    public function test_validate_player_data_invalid_date_format()
    {
        $data = $this->fixtures['valid_player'];
        $data['dob'] = 'invalid-date';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('dob', $errors);
    }

    /**
     * Test validate_player_data with player too young
     */
    public function test_validate_player_data_player_too_young()
    {
        $result = $this->validator->validate_player_data($this->fixtures['player_too_young']);
        $this->assertFalse($result, 'Player under 3 years should fail validation');
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('dob', $errors);
    }

    /**
     * Test validate_player_data with player too old
     */
    public function test_validate_player_data_player_too_old()
    {
        $result = $this->validator->validate_player_data($this->fixtures['player_too_old']);
        $this->assertFalse($result, 'Player over 18 years should fail validation');
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('dob', $errors);
    }

    /**
     * Test validate_player_data with age boundary - exactly 3 years old
     */
    public function test_validate_player_data_age_boundary_min()
    {
        $data = $this->fixtures['valid_player'];
        $data['dob'] = (new DateTime('today'))->modify('-3 years')->format('Y-m-d');
        
        $result = $this->validator->validate_player_data($data);
        $this->assertTrue($result, 'Exactly 3 years old should pass validation');
    }

    /**
     * Test validate_player_data with age boundary - exactly 13 years old
     */
    public function test_validate_player_data_age_boundary_max()
    {
        $data = $this->fixtures['valid_player'];
        $data['dob'] = (new DateTime('today'))->modify('-13 years')->format('Y-m-d');
        
        $result = $this->validator->validate_player_data($data);
        $this->assertTrue($result, 'Exactly 13 years old should pass validation');
    }

    /**
     * Test validate_player_data rejects players over 13 years old
     */
    public function test_validate_player_data_age_boundary_max_rejects_14()
    {
        $data = $this->fixtures['valid_player'];
        $data['dob'] = (new DateTime('today'))->modify('-14 years')->format('Y-m-d');
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result, 'Exactly 14 years old should fail validation');
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('dob', $errors);
    }

    /**
     * Test validate_player_data with missing gender
     */
    public function test_validate_player_data_missing_gender()
    {
        $data = $this->fixtures['valid_player'];
        $data['gender'] = '';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('gender', $errors);
    }

    /**
     * Test validate_player_data with invalid gender
     */
    public function test_validate_player_data_invalid_gender()
    {
        $result = $this->validator->validate_player_data($this->fixtures['player_invalid_gender']);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('gender', $errors);
    }

    /**
     * Test validate_player_data with all valid gender options
     */
    public function test_validate_player_data_all_gender_options()
    {
        $genders = ['male', 'female', 'other'];
        
        foreach ($genders as $gender) {
            $data = $this->fixtures['valid_player'];
            $data['gender'] = $gender;
            
            $result = $this->validator->validate_player_data($data);
            $this->assertTrue($result, "Gender '$gender' should be valid");
        }
    }

    /**
     * Test validate_player_data with invalid AVS number
     */
    public function test_validate_player_data_invalid_avs_number()
    {
        $data = $this->fixtures['valid_player'];
        $data['avs_number'] = 'invalid';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('avs_number', $errors);
    }

    /**
     * Test validate_player_data with empty AVS number (should pass - it's optional)
     */
    public function test_validate_player_data_empty_avs_number()
    {
        $data = $this->fixtures['valid_player'];
        $data['avs_number'] = '';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertTrue($result, 'Empty AVS number should be allowed (optional field)');
    }

    /**
     * Test validate_player_data with medical conditions
     */
    public function test_validate_player_data_with_medical_conditions()
    {
        $data = $this->fixtures['valid_player'];
        $data['medical_conditions'] = 'Allergies to peanuts';
        
        $result = $this->validator->validate_player_data($data);
        $this->assertTrue($result);
    }

    /**
     * Test validate_event_data with valid data
     */
    public function test_validate_event_data_with_valid_data()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-05',
            'venue' => 'InterSoccer Zurich',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertTrue($result);
    }

    /**
     * Test validate_event_data with all valid activity types
     */
    public function test_validate_event_data_all_activity_types()
    {
        $types = ['camp', 'course', 'birthday'];
        
        foreach ($types as $type) {
            $eventData = [
                'activity_type' => $type,
                'start_date' => '2023-07-01',
                'end_date' => '2023-07-05',
            ];
            
            $result = $this->validator->validate_event_data($eventData);
            $this->assertTrue($result, "Activity type '$type' should be valid");
        }
    }

    /**
     * Test validate_event_data with missing activity type
     */
    public function test_validate_event_data_missing_activity_type()
    {
        $eventData = [
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-05',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('activity_type', $errors);
    }

    /**
     * Test validate_event_data with invalid activity type
     */
    public function test_validate_event_data_invalid_activity_type()
    {
        $eventData = [
            'activity_type' => 'invalid',
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-05',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('activity_type', $errors);
    }

    /**
     * Test validate_event_data with invalid start date format
     */
    public function test_validate_event_data_invalid_start_date()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => 'invalid',
            'end_date' => '2023-07-05',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('start_date', $errors);
    }

    /**
     * Test validate_event_data with invalid end date format
     */
    public function test_validate_event_data_invalid_end_date()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-01',
            'end_date' => 'invalid',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('end_date', $errors);
    }

    /**
     * Test validate_event_data with end date before start date
     */
    public function test_validate_event_data_end_date_before_start_date()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-05',
            'end_date' => '2023-07-01',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('date_range', $errors);
    }

    /**
     * Test validate_event_data with same start and end date
     */
    public function test_validate_event_data_same_start_and_end_date()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-01',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result, 'Same start and end date should fail');
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('date_range', $errors);
    }

    /**
     * Test validate_event_data with venue too long
     */
    public function test_validate_event_data_venue_too_long()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-05',
            'venue' => str_repeat('a', 256),  // 256 characters, max is 255
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('venue', $errors);
    }

    /**
     * Test validate_event_data with exactly 255 character venue (boundary)
     */
    public function test_validate_event_data_venue_max_length()
    {
        $eventData = [
            'activity_type' => 'camp',
            'start_date' => '2023-07-01',
            'end_date' => '2023-07-05',
            'venue' => str_repeat('a', 255),  // Exactly 255 characters
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertTrue($result, 'Venue with exactly 255 characters should be valid');
    }

    /**
     * Test validate_event_data without optional fields
     */
    public function test_validate_event_data_minimal_required_fields()
    {
        $eventData = [
            'activity_type' => 'camp',
        ];
        
        $result = $this->validator->validate_event_data($eventData);
        $this->assertTrue($result, 'Only activity_type is required, dates are optional');
    }

    /**
     * Test validate_user_data with valid data
     */
    public function test_validate_user_data_with_valid_data()
    {
        WP_Mock::userFunction('is_email', [
            'args' => ['test@example.com'],
            'return' => true,
        ]);
        
        WP_Mock::userFunction('email_exists', [
            'args' => ['test@example.com'],
            'return' => false,
        ]);
        
        $userData = [
            'email' => 'test@example.com',
            'phone' => '+41 79 123 45 67',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertTrue($result);
    }

    /**
     * Test validate_user_data with missing email
     */
    public function test_validate_user_data_missing_email()
    {
        $userData = [
            'phone' => '+41 79 123 45 67',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * Test validate_user_data with invalid email format
     */
    public function test_validate_user_data_invalid_email_format()
    {
        WP_Mock::userFunction('is_email', [
            'args' => ['invalid-email'],
            'return' => false,
        ]);
        
        $userData = [
            'email' => 'invalid-email',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * Test validate_user_data with existing email
     */
    public function test_validate_user_data_existing_email()
    {
        WP_Mock::userFunction('is_email', [
            'args' => ['existing@example.com'],
            'return' => true,
        ]);
        
        WP_Mock::userFunction('email_exists', [
            'args' => ['existing@example.com'],
            'return' => true,
        ]);
        
        $userData = [
            'email' => 'existing@example.com',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * Test validate_user_data with invalid phone number
     */
    public function test_validate_user_data_invalid_phone()
    {
        WP_Mock::userFunction('is_email', [
            'args' => ['test@example.com'],
            'return' => true,
        ]);
        
        WP_Mock::userFunction('email_exists', [
            'args' => ['test@example.com'],
            'return' => false,
        ]);
        
        $userData = [
            'email' => 'test@example.com',
            'phone' => 'invalid-phone',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertFalse($result);
        
        $errors = $this->validator->get_errors();
        $this->assertArrayHasKey('phone', $errors);
    }

    /**
     * Test validate_user_data without phone (optional field)
     */
    public function test_validate_user_data_without_phone()
    {
        WP_Mock::userFunction('is_email', [
            'args' => ['test@example.com'],
            'return' => true,
        ]);
        
        WP_Mock::userFunction('email_exists', [
            'args' => ['test@example.com'],
            'return' => false,
        ]);
        
        $userData = [
            'email' => 'test@example.com',
        ];
        
        $result = $this->validator->validate_user_data($userData);
        $this->assertTrue($result, 'Phone is optional');
    }

    /**
     * Test date validation with different formats
     */
    public function test_date_validation_supports_multiple_formats()
    {
        $formats = [
            '2023-07-15',           // Y-m-d
            '15/07/2023',           // d/m/Y
            '07/15/2023',           // m/d/Y
            '2023-07-15 10:30:00',  // Y-m-d H:i:s
        ];
        
        foreach ($formats as $format) {
            $data = $this->fixtures['valid_player'];
            $data['dob'] = $format;
            
            // Adjust to valid age
            if ($format === '2023-07-15' || $format === '2023-07-15 10:30:00') {
                $data['dob'] = '2015-07-15';
            } elseif ($format === '15/07/2023') {
                $data['dob'] = '15/07/2015';
            } elseif ($format === '07/15/2023') {
                $data['dob'] = '07/15/2015';
            }
            
            $result = $this->validator->validate_player_data($data);
            $this->assertTrue($result, "Date format '$format' should be supported");
        }
    }
}


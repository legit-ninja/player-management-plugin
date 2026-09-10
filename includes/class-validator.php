<?php
/**
 * InterSoccer Player Management Validator
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validator class for data validation and sanitization
 */
class InterSoccer_Player_Validator {

    /**
     * Validation errors
     */
    private $errors = array();

    /**
     * Validate player data
     *
     * @param array $data Player data
     * @return bool
     */
    public function validate_player_data($data) {
        $this->errors = array();

        // Validate first name
        if (empty($data['first_name'])) {
            $this->add_error('first_name', __('First name is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!$this->validate_name($data['first_name'])) {
            $this->add_error('first_name', __('First name contains invalid characters.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate last name
        if (empty($data['last_name'])) {
            $this->add_error('last_name', __('Last name is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!$this->validate_name($data['last_name'])) {
            $this->add_error('last_name', __('Last name contains invalid characters.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate date of birth
        if (empty($data['dob'])) {
            $this->add_error('dob', __('Date of birth is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!$this->validate_date($data['dob'])) {
            $this->add_error('dob', __('Invalid date of birth format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!$this->validate_age($data['dob'])) {
            $this->add_error('dob', __('Player age is not within acceptable range.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate gender
        if (empty($data['gender'])) {
            $this->add_error('gender', __('Gender is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!in_array($data['gender'], array('male', 'female', 'other'))) {
            $this->add_error('gender', __('Invalid gender selection.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate AVS number (Swiss social security number)
        if (!empty($data['avs_number']) && !$this->validate_avs_number($data['avs_number'])) {
            $this->add_error('avs_number', __('Invalid AVS number format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate medical conditions (optional but must be safe)
        if (!empty($data['medical_conditions'])) {
            $data['medical_conditions'] = $this->sanitize_text($data['medical_conditions']);
        }

        return empty($this->errors);
    }

    /**
     * Validate event data
     *
     * @param array $data Event data
     * @return bool
     */
    public function validate_event_data($data) {
        $this->errors = array();

        // Validate event type
        if (empty($data['activity_type'])) {
            $this->add_error('activity_type', __('Activity type is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!in_array($data['activity_type'], array('camp', 'course', 'birthday'))) {
            $this->add_error('activity_type', __('Invalid activity type.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate dates
        if (!empty($data['start_date']) && !$this->validate_date($data['start_date'])) {
            $this->add_error('start_date', __('Invalid start date format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        if (!empty($data['end_date']) && !$this->validate_date($data['end_date'])) {
            $this->add_error('end_date', __('Invalid end date format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate date range
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                $this->add_error('date_range', __('End date must be after start date.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
            }
        }

        // Validate venue
        if (!empty($data['venue']) && strlen($data['venue']) > 255) {
            $this->add_error('venue', __('Venue name is too long.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        return empty($this->errors);
    }

    /**
     * Validate user registration data
     *
     * @param array $data User data
     * @return bool
     */
    public function validate_user_data($data) {
        $this->errors = array();

        // Validate email
        if (empty($data['email'])) {
            $this->add_error('email', __('Email address is required.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (!is_email($data['email'])) {
            $this->add_error('email', __('Invalid email address format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        } elseif (email_exists($data['email'])) {
            $this->add_error('email', __('Email address is already registered.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        // Validate phone number
        if (!empty($data['phone']) && !$this->validate_phone($data['phone'])) {
            $this->add_error('phone', __('Invalid phone number format.', INTERSOCCER_PLAYER_TEXT_DOMAIN));
        }

        return empty($this->errors);
    }

    /**
     * Validate name (first/last)
     *
     * @param string $name
     * @return bool
     */
    private function validate_name($name) {
        // Allow letters, spaces, hyphens, apostrophes, and some accented characters
        return preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/u', $name) && strlen($name) <= 50;
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @return bool
     */
    private function validate_date($date) {
        $formats = array('Y-m-d', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s');
        
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate age range (3-13 years old)
     *
     * @param string $dob Date of birth
     * @return bool
     */
    private function validate_age($dob) {
        if (!function_exists('intersoccer_calculate_player_age')) {
            return false;
        }

        $age = intersoccer_calculate_player_age($dob);
        return intersoccer_is_valid_player_age($age);
    }

    /**
     * Validate Swiss AVS number format
     *
     * @param string $avs_number
     * @return bool
     */
    private function validate_avs_number($avs_number) {
        // Swiss AVS format: 756.1234.5678.90 or 7561234567890
        $pattern = '/^756[\.\s]?\d{4}[\.\s]?\d{4}[\.\s]?\d{2}$/';
        return preg_match($pattern, $avs_number);
    }

    /**
     * Validate phone number format
     *
     * @param string $phone
     * @return bool
     */
    private function validate_phone($phone) {
        // Allow international formats: +41 79 123 45 67, +33612345678, etc.
        $pattern = '/^\+?\d{1,3}[\s\-\.]?\(?\d{1,4}\)?[\s\-\.]?\d{1,4}[\s\-\.]?\d{1,4}[\s\-\.]?\d{1,9}$/';
        return preg_match($pattern, $phone);
    }

    /**
     * Add validation error
     *
     * @param string $field
     * @param string $message
     */
    private function add_error($field, $message) {
        $this->errors[$field] = $message;
    }

    /**
     * Get all validation errors
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Sanitize text input
     *
     * @param string $text
     * @return string
     */
    private function sanitize_text($text) {
        return sanitize_text_field($text);
    }

    /**
     * Sanitize player data for database storage.
     *
     * @param array $data Raw player data.
     * @return array Sanitized player data.
     */
    public function sanitize_player_data(array $data) {
        return [
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'dob' => isset($data['dob']) ? sanitize_text_field($data['dob']) : '',
            'gender' => isset($data['gender']) && in_array($data['gender'], ['male', 'female', 'other'], true)
                ? $data['gender']
                : 'other',
            'avs_number' => isset($data['avs_number']) ? sanitize_text_field($data['avs_number']) : '',
            'medical_conditions' => isset($data['medical_conditions']) ? sanitize_textarea_field($data['medical_conditions']) : '',
            'dietary_requirements' => isset($data['dietary_requirements']) ? sanitize_textarea_field($data['dietary_requirements']) : '',
            'emergency_contact' => isset($data['emergency_contact']) ? sanitize_text_field($data['emergency_contact']) : '',
            'emergency_phone' => isset($data['emergency_phone']) ? sanitize_text_field($data['emergency_phone']) : '',
            'creation_timestamp' => isset($data['creation_timestamp']) ? absint($data['creation_timestamp']) : time(),
            'event_count' => isset($data['event_count']) ? absint($data['event_count']) : 0,
        ];
    }

    /**
     * Sanitize order/event data for database storage.
     *
     * @param array $data Raw order/event data.
     * @return array Sanitized order/event data.
     */
    public function sanitize_order_data(array $data) {
        $valid_activity_types = ['camp', 'course', 'birthday'];

        return [
            'player_id' => isset($data['player_id']) ? absint($data['player_id']) : 0,
            'order_id' => isset($data['order_id']) ? absint($data['order_id']) : 0,
            'product_id' => isset($data['product_id']) ? absint($data['product_id']) : 0,
            'variation_id' => isset($data['variation_id']) ? absint($data['variation_id']) : null,
            'activity_type' => isset($data['activity_type']) && in_array($data['activity_type'], $valid_activity_types, true)
                ? $data['activity_type']
                : 'camp',
            'event_name' => isset($data['event_name']) ? sanitize_text_field($data['event_name']) : '',
            'venue' => isset($data['venue']) ? sanitize_text_field($data['venue']) : '',
            'age_group' => isset($data['age_group']) ? sanitize_text_field($data['age_group']) : '',
            'season' => isset($data['season']) ? sanitize_text_field($data['season']) : '',
            'start_date' => isset($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => isset($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'booking_type' => isset($data['booking_type']) ? sanitize_text_field($data['booking_type']) : '',
            'selected_days' => isset($data['selected_days']) ? $data['selected_days'] : '',
            'event_times' => isset($data['event_times']) ? sanitize_text_field($data['event_times']) : '',
            'canton' => isset($data['canton']) ? sanitize_text_field($data['canton']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
        ];
    }
}
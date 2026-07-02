<?php
/**
 * InterSoccer Player Management Database
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for managing plugin tables and data
 */
class InterSoccer_Player_Database {

    /**
     * Database version
     */
    const DB_VERSION = '2.0.0';

    /**
     * Table names
     */
    private $tables = array();

    /**
     * Logger instance
     */
    private $logger;

    /**
     * WordPress database instance
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->wpdb = $wpdb;
        $this->logger = new InterSoccer_Player_Logger();
        
        // Define table names
        $this->tables = array(
            'players' => $wpdb->prefix . 'intersoccer_players',
            'player_events' => $wpdb->prefix . 'intersoccer_player_events',
            'rosters' => $wpdb->prefix . 'intersoccer_rosters',
            'roster_entries' => $wpdb->prefix . 'intersoccer_roster_entries',
            'player_stats' => $wpdb->prefix . 'intersoccer_player_stats',
        );
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Players table
        $players_sql = "CREATE TABLE {$this->tables['players']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            player_index int(11) NOT NULL DEFAULT 0,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            dob date NOT NULL,
            gender enum('male','female','other') NOT NULL,
            avs_number varchar(20) DEFAULT NULL,
            medical_conditions text DEFAULT NULL,
            dietary_requirements text DEFAULT NULL,
            emergency_contact varchar(100) DEFAULT NULL,
            emergency_phone varchar(20) DEFAULT NULL,
            creation_timestamp int(11) NOT NULL,
            updated_timestamp int(11) NOT NULL,
            event_count int(11) DEFAULT 0,
            status enum('active','inactive','archived') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY user_player (user_id, player_index),
            KEY user_id (user_id),
            KEY status (status),
            KEY dob (dob)
        ) $charset_collate;";

        // Player Events table
        $player_events_sql = "CREATE TABLE {$this->tables['player_events']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            player_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT NULL,
            activity_type enum('camp','course','birthday') NOT NULL,
            event_name varchar(255) NOT NULL,
            venue varchar(255) DEFAULT NULL,
            age_group varchar(50) DEFAULT NULL,
            season varchar(50) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            booking_type varchar(50) DEFAULT NULL,
            selected_days text DEFAULT NULL,
            event_times varchar(100) DEFAULT NULL,
            canton varchar(50) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            status enum('upcoming','active','completed','cancelled') DEFAULT 'upcoming',
            attendance_status enum('registered','attended','absent','partial') DEFAULT 'registered',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY player_id (player_id),
            KEY order_id (order_id),
            KEY activity_type (activity_type),
            KEY start_date (start_date),
            KEY status (status),
            FOREIGN KEY (player_id) REFERENCES {$this->tables['players']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Rosters table
        $rosters_sql = "CREATE TABLE {$this->tables['rosters']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            activity_type enum('camp','course','birthday','all') NOT NULL,
            season varchar(50) DEFAULT NULL,
            venue varchar(255) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            status enum('draft','active','completed','archived') DEFAULT 'draft',
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY activity_type (activity_type),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Roster Entries table
        $roster_entries_sql = "CREATE TABLE {$this->tables['roster_entries']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            roster_id bigint(20) NOT NULL,
            player_id bigint(20) NOT NULL,
            event_id bigint(20) NOT NULL,
            position int(11) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY roster_player_event (roster_id, player_id, event_id),
            KEY roster_id (roster_id),
            KEY player_id (player_id),
            KEY event_id (event_id),
            FOREIGN KEY (roster_id) REFERENCES {$this->tables['rosters']}(id) ON DELETE CASCADE,
            FOREIGN KEY (player_id) REFERENCES {$this->tables['players']}(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES {$this->tables['player_events']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Player Stats table
        $player_stats_sql = "CREATE TABLE {$this->tables['player_stats']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            player_id bigint(20) NOT NULL,
            stat_type varchar(50) NOT NULL,
            stat_value text NOT NULL,
            season varchar(50) DEFAULT NULL,
            event_type varchar(50) DEFAULT NULL,
            recorded_date date NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY player_id (player_id),
            KEY stat_type (stat_type),
            KEY season (season),
            FOREIGN KEY (player_id) REFERENCES {$this->tables['players']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($players_sql);
        dbDelta($player_events_sql);
        dbDelta($rosters_sql);
        dbDelta($roster_entries_sql);
        dbDelta($player_stats_sql);

        update_option('intersoccer_player_db_version', self::DB_VERSION);
        
        $this->logger->info('Database tables created successfully');
    }

    /**
     * Upgrade database schema
     */
    public function upgrade_schema() {
        $current_version = get_option('intersoccer_player_db_version', '1.0.0');
        
        if (version_compare($current_version, '2.0.0', '<')) {
            // Create new tables
            $this->create_tables();
            
            // Migrate data from user meta
            $this->migrate_player_data();
        }
        
        $this->logger->info('Database schema upgraded from ' . $current_version . ' to ' . self::DB_VERSION);
    }

    /**
     * Migrate player data from user meta to database tables
     */
    public function migrate_player_data() {
        $users_with_players = $this->wpdb->get_results(
            "SELECT user_id, meta_value 
             FROM {$this->wpdb->usermeta} 
             WHERE meta_key = 'intersoccer_players'"
        );

        $migrated_count = 0;
        
        foreach ($users_with_players as $user_data) {
            $players = maybe_unserialize($user_data->meta_value);
            
            if (is_array($players)) {
                foreach ($players as $index => $player_data) {
                    $this->create_player($user_data->user_id, $index, $player_data);
                    $migrated_count++;
                }
            }
        }
        
        $this->logger->info("Migrated {$migrated_count} players from user meta to database tables");
    }

    /**
     * Create a new player record
     *
     * @param int $user_id
     * @param int $player_index
     * @param array $player_data
     * @return int|false Player ID on success, false on failure
     */
    public function create_player($user_id, $player_index, array $player_data) {
        $validator = new InterSoccer_Player_Validator();
        $sanitized_data = $validator->sanitize_player_data($player_data);
        
        $result = $this->wpdb->insert(
            $this->tables['players'],
            array(
                'user_id' => $user_id,
                'player_index' => $player_index,
                'first_name' => $sanitized_data['first_name'],
                'last_name' => $sanitized_data['last_name'],
                'dob' => $sanitized_data['dob'],
                'gender' => $sanitized_data['gender'],
                'avs_number' => $sanitized_data['avs_number'],
                'medical_conditions' => $sanitized_data['medical_conditions'],
                'dietary_requirements' => $sanitized_data['dietary_requirements'],
                'emergency_contact' => $sanitized_data['emergency_contact'],
                'emergency_phone' => $sanitized_data['emergency_phone'],
                'creation_timestamp' => $sanitized_data['creation_timestamp'],
                'updated_timestamp' => time(),
                'event_count' => $sanitized_data['event_count'],
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to create player', array(
                'user_id' => $user_id,
                'player_index' => $player_index,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }

        $player_id = $this->wpdb->insert_id;
        $this->logger->debug('Player created', array(
            'player_id' => $player_id,
            'user_id' => $user_id,
            'player_index' => $player_index
        ));

        return $player_id;
    }

    /**
     * Update player record
     *
     * @param int $player_id
     * @param array $player_data
     * @return bool
     */
    public function update_player($player_id, array $player_data) {
        $validator = new InterSoccer_Player_Validator();
        $sanitized_data = $validator->sanitize_player_data($player_data);
        $sanitized_data['updated_timestamp'] = time();

        $result = $this->wpdb->update(
            $this->tables['players'],
            $sanitized_data,
            array('id' => $player_id),
            null,
            array('%d')
        );

        $success = $result !== false;
        $this->logger->log_database_operation('update', 'players', array('player_id' => $player_id), $success);

        return $success;
    }

    /**
     * Get player by ID
     *
     * @param int $player_id
     * @return object|null
     */
    public function get_player($player_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['players']} WHERE id = %d AND status = 'active'",
                $player_id
            )
        );
    }

    /**
     * Get players by user ID
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_players($user_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['players']} 
                 WHERE user_id = %d AND status = 'active' 
                 ORDER BY player_index ASC",
                $user_id
            )
        );
    }

    /**
     * Delete player (soft delete)
     *
     * @param int $player_id
     * @return bool
     */
    public function delete_player($player_id) {
        $result = $this->wpdb->update(
            $this->tables['players'],
            array(
                'status' => 'archived',
                'updated_timestamp' => time()
            ),
            array('id' => $player_id),
            array('%s', '%d'),
            array('%d')
        );

        $success = $result !== false;
        $this->logger->log_database_operation('delete', 'players', array('player_id' => $player_id), $success);

        return $success;
    }

    /**
     * Create player event record
     *
     * @param array $event_data
     * @return int|false Event ID on success, false on failure
     */
    public function create_player_event(array $event_data) {
        $validator = new InterSoccer_Player_Validator();
        $sanitized_data = $validator->sanitize_order_data($event_data);

        $result = $this->wpdb->insert(
            $this->tables['player_events'],
            array(
                'player_id' => $sanitized_data['player_id'] ?? 0,
                'order_id' => $sanitized_data['order_id'],
                'product_id' => $sanitized_data['product_id'],
                'variation_id' => $sanitized_data['variation_id'],
                'activity_type' => $sanitized_data['activity_type'],
                'event_name' => $sanitized_data['event_name'] ?? '',
                'venue' => $sanitized_data['venue'],
                'age_group' => $sanitized_data['age_group'],
                'season' => $sanitized_data['season'],
                'start_date' => $sanitized_data['start_date'],
                'end_date' => $sanitized_data['end_date'],
                'booking_type' => $sanitized_data['booking_type'],
                'selected_days' => is_array($sanitized_data['selected_days']) ? 
                    implode(',', $sanitized_data['selected_days']) : $sanitized_data['selected_days'],
                'event_times' => $sanitized_data['event_times'] ?? '',
                'canton' => $sanitized_data['canton'] ?? '',
                'city' => $sanitized_data['city'],
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $this->logger->error('Failed to create player event', array(
                'event_data' => $sanitized_data,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get player events
     *
     * @param int $player_id
     * @param array $filters
     * @return array
     */
    public function get_player_events($player_id, array $filters = array()) {
        $where = array("player_id = %d");
        $values = array($player_id);

        if (!empty($filters['activity_type'])) {
            $where[] = "activity_type = %s";
            $values[] = $filters['activity_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = %s";
            $values[] = $filters['status'];
        }

        if (!empty($filters['season'])) {
            $where[] = "season = %s";
            $values[] = $filters['season'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "start_date >= %s";
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "end_date <= %s";
            $values[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = "ORDER BY start_date DESC";

        if (!empty($filters['limit'])) {
            $order_clause .= " LIMIT " . absint($filters['limit']);
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['player_events']} 
                 WHERE {$where_clause} {$order_clause}",
                ...$values
            )
        );
    }

    /**
     * Get roster data for export
     *
     * @param array $filters
     * @return array
     */
    public function get_roster_data(array $filters = array()) {
        $select = "
            p.id as player_id,
            p.first_name,
            p.last_name,
            p.dob,
            p.gender,
            p.avs_number,
            p.medical_conditions,
            p.dietary_requirements,
            p.emergency_contact,
            p.emergency_phone,
            u.user_email as parent_email,
            u.display_name as parent_name,
            um_phone.meta_value as parent_phone,
            um_city.meta_value as parent_city,
            um_state.meta_value as parent_canton,
            pe.activity_type,
            pe.event_name,
            pe.venue,
            pe.age_group,
            pe.season,
            pe.start_date,
            pe.end_date,
            pe.booking_type,
            pe.selected_days,
            pe.event_times,
            pe.canton as event_canton,
            pe.city as event_city,
            pe.status as event_status
        ";

        $from = "
            FROM {$this->tables['players']} p
            INNER JOIN {$this->tables['player_events']} pe ON p.id = pe.player_id
            INNER JOIN {$this->wpdb->users} u ON p.user_id = u.ID
            LEFT JOIN {$this->wpdb->usermeta} um_phone ON u.ID = um_phone.user_id AND um_phone.meta_key = 'billing_phone'
            LEFT JOIN {$this->wpdb->usermeta} um_city ON u.ID = um_city.user_id AND um_city.meta_key = 'billing_city'
            LEFT JOIN {$this->wpdb->usermeta} um_state ON u.ID = um_state.user_id AND um_state.meta_key = 'billing_state'
        ";

        $where = array("p.status = 'active'");
        $values = array();

        // Apply filters
        if (!empty($filters['activity_type'])) {
            if (is_array($filters['activity_type'])) {
                $placeholders = implode(',', array_fill(0, count($filters['activity_type']), '%s'));
                $where[] = "pe.activity_type IN ({$placeholders})";
                $values = array_merge($values, $filters['activity_type']);
            } else {
                $where[] = "pe.activity_type = %s";
                $values[] = $filters['activity_type'];
            }
        }

        if (!empty($filters['season'])) {
            $where[] = "pe.season = %s";
            $values[] = $filters['season'];
        }

        if (!empty($filters['venue'])) {
            $where[] = "pe.venue = %s";
            $values[] = $filters['venue'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "pe.start_date >= %s";
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "pe.end_date <= %s";
            $values[] = $filters['date_to'];
        }

        if (!empty($filters['canton'])) {
            $where[] = "(pe.canton = %s OR um_state.meta_value = %s)";
            $values[] = $filters['canton'];
            $values[] = $filters['canton'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = "ORDER BY pe.start_date ASC, p.last_name ASC, p.first_name ASC";

        $sql = "SELECT {$select} {$from} WHERE {$where_clause} {$order_clause}";

        if (empty($values)) {
            return $this->wpdb->get_results($sql);
        } else {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, ...$values));
        }
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public function get_statistics() {
        $stats = array();

        // Total active players
        $stats['total_players'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['players']} WHERE status = 'active'"
        );

        // Players by gender
        $gender_stats = $this->wpdb->get_results(
            "SELECT gender, COUNT(*) as count 
             FROM {$this->tables['players']} 
             WHERE status = 'active' 
             GROUP BY gender"
        );
        $stats['gender_breakdown'] = array();
        foreach ($gender_stats as $gender_stat) {
            $stats['gender_breakdown'][$gender_stat->gender] = $gender_stat->count;
        }

        // Events by type
        $event_stats = $this->wpdb->get_results(
            "SELECT activity_type, COUNT(*) as count 
             FROM {$this->tables['player_events']} 
             GROUP BY activity_type"
        );
        $stats['events_by_type'] = array();
        foreach ($event_stats as $event_stat) {
            $stats['events_by_type'][$event_stat->activity_type] = $event_stat->count;
        }

        // Recent registrations (last 30 days)
        $stats['recent_registrations'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['players']} 
                 WHERE status = 'active' AND creation_timestamp > %d",
                time() - (30 * DAY_IN_SECONDS)
            )
        );

        // Average age
        $stats['average_age'] = $this->wpdb->get_var(
            "SELECT AVG(YEAR(CURDATE()) - YEAR(dob)) as avg_age 
             FROM {$this->tables['players']} 
             WHERE status = 'active'"
        );

        return $stats;
    }

    /**
     * Cleanup old data
     *
     * @param int $days_old
     * @return int Number of records cleaned
     */
    public function cleanup_old_data($days_old = 365) {
        $cutoff_timestamp = time() - ($days_old * DAY_IN_SECONDS);
        
        // Archive old players with no recent events
        $archived_players = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->tables['players']} 
                 SET status = 'archived', updated_timestamp = %d
                 WHERE status = 'active' 
                 AND creation_timestamp < %d
                 AND id NOT IN (
                     SELECT DISTINCT player_id 
                     FROM {$this->tables['player_events']} 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 )",
                time(),
                $cutoff_timestamp,
                $days_old
            )
        );

        $this->logger->info("Archived {$archived_players} old player records");
        
        return $archived_players;
    }

    /**
     * Get table name
     *
     * @param string $table
     * @return string
     */
    public function get_table_name($table) {
        return $this->tables[$table] ?? '';
    }

    /**
     * Get all table names
     *
     * @return array
     */
    public function get_table_names() {
        return $this->tables;
    }

    /**
     * Check database health
     *
     * @return array
     */
    public function check_database_health() {
        $health = array(
            'tables_exist' => true,
            'tables_status' => array(),
            'total_players' => 0,
            'total_events' => 0,
            'errors' => array()
        );

        // Check if all tables exist
        foreach ($this->tables as $table_key => $table_name) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            ) === $table_name;
            
            $health['tables_status'][$table_key] = $exists;
            
            if (!$exists) {
                $health['tables_exist'] = false;
                $health['errors'][] = "Table {$table_name} does not exist";
            }
        }

        if ($health['tables_exist']) {
            $health['total_players'] = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['players']}"
            );
            
            $health['total_events'] = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['player_events']}"
            );
        }

        return $health;
    }
}
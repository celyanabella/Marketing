<?php

namespace {
	/**
	 * Class WP_Statistics
	 *
	 * This is the primary class for WP Statistics recording hits on the WordPress site.
	 * It is extended by the Hits class and the GEO_IP_Hits class.
	 * This class handles; visits, visitors and pages.
	 */
	class WP_Statistics {

		// Setup our protected, private and public variables.
		protected $db;
		protected $tb_prefix;
		protected $ip      = false;
		protected $ip_hash = false;
		protected $agent;

		private $result;
		private $historical;
		private $user_options_loaded = false;
		private $is_feed             = false;
		private $tz_offset           = 0;
		private $country_codes       = false;
		private $referrer            = false;

		public $coefficient  = 1;
		public $user_id      = 0;
		public $options      = array();
		public $user_options = array();
		public $menu_slugs   = array();
		public $installed_version;

		public static $reg  = array();
		public static $page = array();

		/**
		 * __construct
		 * WP_Statistics constructor.
		 */
		public function __construct() {
			global $wpdb;

			if ( ! isset( WP_Statistics::$reg['plugin-url'] ) ) {
				/**
				 * Plugin URL
				 */
				WP_Statistics::$reg['plugin-url'] = plugin_dir_url(WP_STATISTICS_MAIN_FILE);
				//define('WP_STATISTICS_PLUGIN_URL', plugin_dir_url(WP_STATISTICS_MAIN_FILE));
				/**
				 * Plugin DIR
				 */
				WP_Statistics::$reg['plugin-dir'] = plugin_dir_path(WP_STATISTICS_MAIN_FILE);
				//define('WP_STATISTICS_PLUGIN_DIR', plugin_dir_path(WP_STATISTICS_MAIN_FILE));
				/**
				 * Plugin Main File
				 */
				WP_Statistics::$reg['main-file'] = WP_STATISTICS_MAIN_FILE;
				/**
				 * WP Statistics Version
				 */

				if ( ! function_exists( 'get_plugin_data' ) ){
					require( ABSPATH . 'wp-admin/includes/plugin.php' );
				}
				WP_Statistics::$reg['plugin-data'] = get_plugin_data( WP_STATISTICS_MAIN_FILE );
				WP_Statistics::$reg['version'] = WP_Statistics::$reg['plugin-data']['Version'];
				//define('WP_STATISTICS_VERSION', '12.1.3');

				/**
				 * Required PHP Version
				 */
				WP_Statistics::$reg['required-php-version'] = '5.4.0';
				//define('WP_STATISTICS_REQUIRED_PHP_VERSION', '5.4.0');
				/**
				 * Required GEO IP PHP Version
				 */
				WP_Statistics::$reg['geoip-php-version'] = WP_Statistics::$reg['required-php-version'];
				//define('WP_STATISTICS_REQUIRED_GEOIP_PHP_VERSION', WP_Statistics::$reg['required_php_version']);

				$this->set_pages();
			}

			require( WP_Statistics::$reg['plugin-dir'] . 'includes/vendor/autoload.php' );

			// define an autoload method to automatically load classes in /includes/classes
			spl_autoload_register(array( $this, 'autoload' ));

			// Add init actions.
			// For the main init we're going to set our priority to 9 to execute before most plugins
			// so we can export data before and set the headers without
			// worrying about bugs in other plugins that output text and don't allow us to set the headers.
			add_action('init', array( $this, 'init' ), 9);

			// Check the PHP version,
			// if we don't meet the minimum version to run WP Statistics return so we don't cause a critical error.
			if ( ! version_compare(phpversion(), WP_Statistics::$reg['required-php-version'], ">=") ) {
				add_action('admin_notices', array( $this, 'unsupported_version_admin_notice' ), 10, 2);

				return;
			}

			// If we've been flagged to remove all of the data, then do so now.
			if ( get_option('wp_statistics_removal') == 'true' ) {
				new \WP_Statistics_Uninstall;
			}

			// If we've been removed, return without doing anything else.
			if ( get_option('wp_statistics_removal') == 'done' ) {
				add_action('admin_notices', array( $this, 'removal_admin_notice' ), 10, 2);

				return;
			}

			if ( get_option('timezone_string') ) {
				$this->tz_offset = timezone_offset_get(timezone_open(get_option('timezone_string')), new DateTime());
			} else if ( get_option('gmt_offset') ) {
				$this->tz_offset = get_option('gmt_offset') * 60 * 60;
			}

			$this->db         = $wpdb;
			$this->tb_prefix  = $wpdb->prefix;
			$this->agent      = $this->get_UserAgent();
			$this->historical = array();

			// Load the options from the database
			$this->options = get_option('wp_statistics');

			if ( ! is_array($this->options) ) {
				$this->user_options = array();
			}

			// Set the default co-efficient.
			$this->coefficient = $this->get_option('coefficient', 1);

			// Double check the co-efficient setting to make sure it's not been set to 0.
			if ( $this->coefficient <= 0 ) {
				$this->coefficient = 1;
			}

			$this->get_IP();

			if ( $this->get_option('hash_ips') == true ) {
				$this->ip_hash = '#hash#' . sha1($this->ip . $_SERVER['HTTP_USER_AGENT']);
			}

			// Check to see if we're installed and are the current version.
			$this->installed_version = get_option('wp_statistics_plugin_version');
			if ( $this->installed_version != WP_Statistics::$reg['version'] ) {
				new \WP_Statistics_Install($this);
			}

			// Load the rest of the required files for our global functions, online user tracking and hit tracking.
			if ( ! function_exists('wp_statistics_useronline') ) {
				include WP_Statistics::$reg['plugin-dir'] . 'includes/functions/functions.php';
			}


			add_action('widgets_init', array( $this, 'widget' ));
			add_action('wp_dashboard_setup', 'WP_Statistics_Dashboard::widget_load');
			add_action('admin_footer', 'WP_Statistics_Dashboard::inline_javascript');

			add_action('add_meta_boxes', 'WP_Statistics_Editor::add_meta_box');

			add_shortcode('wpstatistics', 'WP_Statistics_Shortcode::shortcodes');
			add_filter('widget_text', 'do_shortcode');
			add_action('admin_init', 'WP_Statistics_Shortcode::shortcake');

			new \WP_Statistics_Schedule($this);
			new \WP_Statistics_Ajax();

			new \WP_Statistics_Bootstrap($this);
		}

		/**
		 * Autoload classes of the plugin
		 *
		 * @param string $class Class name
		 */
		public function autoload( $class ) {
			if ( ! class_exists($class) &&
			     // This check is for performance of loading plugin classes
			     substr($class, 0, 14) === 'WP_Statistics_'
			) {
				$lower_class_name = str_replace('_', '-', strtolower($class));
				$class_full_path  = WP_Statistics::$reg['plugin-dir'] .
				                    'includes/classes/class-' .
				                    $lower_class_name .
				                    '.php';
				if ( file_exists($class_full_path) ) {
					require $class_full_path;
				}
			}
		}

		public function set_pages() {
			if ( ! isset( WP_Statistics::$page['overview'] ) ) {
				/**
				 * Overview Page
				 */
				WP_Statistics::$page['overview'] = 'wps_overview_page';
				//define('WP_STATISTICS_OVERVIEW_PAGE', 'wps_overview_page');
				/**
				 * Browsers Page
				 */
				WP_Statistics::$page['browser'] = 'wps_browsers_page';
				//define('WP_STATISTICS_BROWSERS_PAGE', 'wps_browsers_page');
				/**
				 * Countries Page
				 */
				WP_Statistics::$page['countries'] = 'wps_countries_page';
				//define('WP_STATISTICS_COUNTRIES_PAGE', 'wps_countries_page');
				/**
				 * Exclusions Page
				 */
				WP_Statistics::$page['exclusions'] = 'wps_exclusions_page';
				//define('WP_STATISTICS_EXCLUSIONS_PAGE', 'wps_exclusions_page');
				/**
				 * Hits Page
				 */
				WP_Statistics::$page['hits'] = 'wps_hits_page';
				//define('WP_STATISTICS_HITS_PAGE', 'wps_hits_page');
				/**
				 * Online Page
				 */
				WP_Statistics::$page['online'] = 'wps_online_page';
				//define('WP_STATISTICS_ONLINE_PAGE', 'wps_online_page');
				/**
				 * Pages Page
				 */
				WP_Statistics::$page['pages'] = 'wps_pages_page';
				//define('WP_STATISTICS_PAGES_PAGE', 'wps_pages_page');
				/**
				 * Categories Page
				 */
				WP_Statistics::$page['categories'] = 'wps_categories_page';
				//define('WP_STATISTICS_CATEGORIES_PAGE', 'wps_categories_page');
				/**
				 * Authors Page
				 */
				WP_Statistics::$page['authors'] = 'wps_authors_page';
				//define('WP_STATISTICS_AUTHORS_PAGE', 'wps_authors_page');
				/**
				 * Tags Page
				 */
				WP_Statistics::$page['tags'] = 'wps_tags_page';
				//define('WP_STATISTICS_TAGS_PAGE', 'wps_tags_page');
				/**
				 * Referer Page
				 */
				WP_Statistics::$page['referrers'] = 'wps_referrers_page';
				//define('WP_STATISTICS_REFERRERS_PAGE', 'wps_referrers_page');
				/**
				 * Searched Phrases Page
				 */
				WP_Statistics::$page['searched-phrases'] = 'wps_searched_phrases_page';
				//define('WP_STATISTICS_SEARCHED_PHRASES_PAGE', 'wps_searched_phrases_page');
				/**
				 * Searches Page
				 */
				WP_Statistics::$page['searches'] = 'wps_searches_page';
				//define('WP_STATISTICS_SEARCHES_PAGE', 'wps_searches_page');
				/**
				 * Words Page
				 */
				WP_Statistics::$page['words'] = 'wps_words_page';
				//define('WP_STATISTICS_WORDS_PAGE', 'wps_words_page');
				/**
				 * Top Visitors Page
				 */
				WP_Statistics::$page['top-visitors'] = 'wps_top_visitors_page';
				//define('WP_STATISTICS_TOP_VISITORS_PAGE', 'wps_top_visitors_page');
				/**
				 * Visitors Page
				 */
				WP_Statistics::$page['visitors'] = 'wps_visitors_page';
				//define('WP_STATISTICS_VISITORS_PAGE', 'wps_visitors_page');
				/**
				 * Optimization Page
				 */
				WP_Statistics::$page['optimization'] = 'wps_optimization_page';
				//define('WP_STATISTICS_OPTIMIZATION_PAGE', 'wps_optimization_page');
				/**
				 * Settings Page
				 */
				WP_Statistics::$page['settings'] = 'wps_settings_page';
				//define('WP_STATISTICS_SETTINGS_PAGE', 'wps_settings_page');
				/**
				 * Plugins Page
				 */
				WP_Statistics::$page['plugins'] = 'wps_plugins_page';
				//define('WP_STATISTICS_PLUGINS_PAGE', 'wps_plugins_page');
				/**
				 * Donate Page
				 */
				WP_Statistics::$page['donate'] = 'wps_donate_page';
				//define('WP_STATISTICS_DONATE_PAGE', 'wps_donate_page');
			}
		}

		/**
		 * Loads the init code.
		 */
		public function init() {
			// Check to see if we're exporting data, if so, do so now.
			// Note this will set the headers to download the export file and then stop running WordPress.
			if ( array_key_exists('wps_export', $_POST) ) {
				if ( ! function_exists('wp_statistics_export_data') ) {
					include WP_Statistics::$reg['plugin-dir'] . 'includes/functions/export.php';
				}
				wp_statistics_export_data();
			}
		}

		/**
		 * Registers Widget
		 */
		public function widget() {
			register_widget('WP_Statistics_Widget');
		}

		/**
		 * Unsupported Version Admin Notice
		 */
		public function unsupported_version_admin_notice() {

			$screen = get_current_screen();

			if ( 'plugins' !== $screen->id ) {
				return;
			}
			?>
			<div class="error">
				<p style="max-width:800px;">
					<b><?php _e(
							'WP Statistics Disabled',
							'wp-statistics'
						); ?></b> <?php _e(
						'&#151; You are running an unsupported version of PHP.',
						'wp-statistics'
					); ?>
				</p>

				<p style="max-width:800px;"><?php

					echo sprintf(
						__(
							'WP Statistics has detected PHP version %s which is unsupported, WP Statistics requires PHP Version %s or higher!',
							'wp-statistics'
						),
						phpversion(),
						WP_Statistics::$reg['required-php-version']
					);
					echo '</p><p>';
					echo __(
						'Please contact your hosting provider to upgrade to a supported version or disable WP Statistics to remove this message.',
						'wp-statistics'
					);
					?></p>
			</div>

			<?php
		}

		/**
		 * This adds a row after WP Statistics in the plugin page
		 * IF we've been removed via the settings page.
		 */
		public function removal_admin_notice() {
			$screen = get_current_screen();

			if ( 'plugins' !== $screen->id ) {
				return;
			}

			?>
			<div class="error">
				<p style="max-width:800px;"><?php

					echo '<p>';
					echo __('WP Statistics has been removed, please disable and delete it.', 'wp-statistics');
					echo '</p>';
					?></p>
			</div>
			<?php
		}

		// This function sets the current WordPress user id for the class.
		public function set_user_id() {
			if ( $this->user_id == 0 ) {
				$this->user_id = get_current_user_id();
			}
		}

		// This function loads the options from WordPress, it is included here for completeness as the options are loaded automatically in the class constructor.
		public function load_options() {
			$this->options = get_option('wp_statistics');

			if ( ! is_array($this->options) ) {
				$this->user_options = array();
			}
		}

		// This function loads the user options from WordPress.  It is NOT called during the class constructor.
		public function load_user_options( $force = false ) {
			if ( $this->user_options_loaded == true && $force != true ) {
				return;
			}

			$this->set_user_id();

			// Not sure why, but get_user_meta() is returning an array or array's unless $single is set to true.
			$this->user_options = get_user_meta($this->user_id, 'wp_statistics', true);

			if ( ! is_array($this->user_options) ) {
				$this->user_options = array();
			}

			$this->user_options_loaded = true;
		}

		// The function mimics WordPress's get_option() function but uses the array instead of individual options.
		public function get_option( $option, $default = null ) {
			// If no options array exists, return FALSE.
			if ( ! is_array($this->options) ) {
				return false;
			}

			// if the option isn't set yet, return the $default if it exists, otherwise FALSE.
			if ( ! array_key_exists($option, $this->options) ) {
				if ( isset( $default ) ) {
					return $default;
				} else {
					return false;
				}
			}

			// Return the option.
			return $this->options[ $option ];
		}

		// This function mimics WordPress's get_user_meta() function but uses the array instead of individual options.
		public function get_user_option( $option, $default = null ) {
			// If the user id has not been set or no options array exists, return FALSE.
			if ( $this->user_id == 0 ) {
				return false;
			}
			if ( ! is_array($this->user_options) ) {
				return false;
			}

			// if the option isn't set yet, return the $default if it exists, otherwise FALSE.
			if ( ! array_key_exists($option, $this->user_options) ) {
				if ( isset( $default ) ) {
					return $default;
				} else {
					return false;
				}
			}

			// Return the option.
			return $this->user_options[ $option ];
		}

		// The function mimics WordPress's update_option() function but uses the array instead of individual options.
		public function update_option( $option, $value ) {
			// Store the value in the array.
			$this->options[ $option ] = $value;

			// Write the array to the database.
			update_option('wp_statistics', $this->options);
		}

		// The function mimics WordPress's update_user_meta() function but uses the array instead of individual options.
		public function update_user_option( $option, $value ) {
			// If the user id has not been set return FALSE.
			if ( $this->user_id == 0 ) {
				return false;
			}

			// Store the value in the array.
			$this->user_options[ $option ] = $value;

			// Write the array to the database.
			update_user_meta($this->user_id, 'wp_statistics', $this->user_options);
		}

		// This function is similar to update_option, but it only stores the option in the array.  This save some writing to the database if you have multiple values to update.
		public function store_option( $option, $value ) {
			$this->options[ $option ] = $value;
		}

		// This function is similar to update_user_option, but it only stores the option in the array.  This save some writing to the database if you have multiple values to update.
		public function store_user_option( $option, $value ) {
			// If the user id has not been set return FALSE.
			if ( $this->user_id == 0 ) {
				return false;
			}

			$this->user_options[ $option ] = $value;
		}

		// This function saves the current options array to the database.
		public function save_options() {
			update_option('wp_statistics', $this->options);
		}

		// This function saves the current user options array to the database.
		public function save_user_options() {
			if ( $this->user_id == 0 ) {
				return false;
			}

			update_user_meta($this->user_id, 'wp_statistics', $this->user_options);
		}

		// This function check to see if an option is currently set or not.
		public function isset_option( $option ) {
			if ( ! is_array($this->options) ) {
				return false;
			}

			return array_key_exists($option, $this->options);
		}

		// This function check to see if a user option is currently set or not.
		public function isset_user_option( $option ) {
			if ( $this->user_id == 0 ) {
				return false;
			}
			if ( ! is_array($this->user_options) ) {
				return false;
			}

			return array_key_exists($option, $this->user_options);
		}

		// During installation of WP Statistics some initial data needs to be loaded in to the database so errors are not displayed.
		// This function will add some initial data if the tables are empty.
		public function Primary_Values() {

			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_useronline");

			if ( ! $this->result ) {

				$this->db->insert(
					$this->tb_prefix . "statistics_useronline",
					array(
						'ip'        => $this->get_IP(),
						'timestamp' => $this->Current_Date('U'),
						'date'      => $this->Current_Date(),
						'referred'  => $this->get_Referred(),
						'agent'     => $this->agent['browser'],
						'platform'  => $this->agent['platform'],
						'version'   => $this->agent['version'],
					)
				);
			}

			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_visit");

			if ( ! $this->result ) {

				$this->db->insert(
					$this->tb_prefix . "statistics_visit",
					array(
						'last_visit'   => $this->Current_Date(),
						'last_counter' => $this->Current_date('Y-m-d'),
						'visit'        => 1,
					)
				);
			}

			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_visitor");

			if ( ! $this->result ) {

				$this->db->insert(
					$this->tb_prefix . "statistics_visitor",
					array(
						'last_counter' => $this->Current_date('Y-m-d'),
						'referred'     => $this->get_Referred(),
						'agent'        => $this->agent['browser'],
						'platform'     => $this->agent['platform'],
						'version'      => $this->agent['version'],
						'ip'           => $this->get_IP(),
						'location'     => '000',
					)
				);
			}
		}

		// During installation of WP Statistics some initial options need to be set.
		// This function will save a set of default options for the plugin.
		public function Default_Options() {
			$options = array();

			if ( ! isset( $wps_robotarray ) ) {
				// Get the robots list, we'll use this for both upgrades and new installs.
				include( WP_Statistics::$reg['plugin-dir'] . 'includes/robotslist.php' );
			}

			$options['robotlist'] = trim($wps_robotslist);

			// By default, on new installs, use the new search table.
			$options['search_converted'] = 1;

			// If this is a first time install or an upgrade and we've added options, set some intelligent defaults.
			$options['geoip']                 = false;
			$options['browscap']              = false;
			$options['useronline']            = true;
			$options['visits']                = true;
			$options['visitors']              = true;
			$options['pages']                 = true;
			$options['check_online']          = '30';
			$options['menu_bar']              = false;
			$options['coefficient']           = '1';
			$options['stats_report']          = false;
			$options['time_report']           = 'daily';
			$options['send_report']           = 'mail';
			$options['content_report']        = '';
			$options['update_geoip']          = true;
			$options['store_ua']              = false;
			$options['robotlist']             = $wps_robotslist;
			$options['exclude_administrator'] = true;
			$options['disable_se_clearch']    = true;
			$options['disable_se_ask']        = true;
			$options['map_type']              = 'jqvmap';

			$options['force_robot_update'] = true;

			return $options;
		}

		// This function processes a string that represents an IP address and returns either FALSE if it's invalid or a valid IP4 address.
		private function get_ip_value( $ip ) {
			// Reject anything that's not a string.
			if ( ! is_string($ip) ) {
				return false;
			}

			// Trim off any spaces.
			$ip = trim($ip);

			// Process IPv4 and v6 addresses separately.
			if ( $this->isValidIPv6($ip) ) {
				// Reject any IPv6 addresses if IPv6 is not compiled in to this version of PHP.
				if ( ! defined('AF_INET6') ) {
					return false;
				}
			} else {
				// Trim off any port values that exist.
				if ( strstr($ip, ':') !== false ) {
					$temp = explode(':', $ip);
					$ip   = $temp[0];
				}

				// Check to make sure the http header is actually an IP address and not some kind of SQL injection attack.
				$long = ip2long($ip);

				// ip2long returns either -1 or FALSE if it is not a valid IP address depending on the PHP version, so check for both.
				if ( $long == -1 || $long === false ) {
					return false;
				}
			}

			// If the ip address is blank, reject it.
			if ( $ip == '' ) {
				return false;
			}

			// We're got a real IP address, return it.
			return $ip;

		}

		// This function returns the current IP address of the remote client.
		public function get_IP() {

			// Check to see if we've already retrieved the IP address and if so return the last result.
			if ( $this->ip !== false ) {
				return $this->ip;
			}

			// By default we use the remote address the server has.
			if ( array_key_exists('REMOTE_ADDR', $_SERVER) ) {
				$temp_ip = $this->get_ip_value($_SERVER['REMOTE_ADDR']);
			} else {
				$temp_ip = '127.0.0.1';
			}

			if ( false !== $temp_ip ) {
				$this->ip = $temp_ip;
			}

			/* Check to see if any of the HTTP headers are set to identify the remote user.
			 * These often give better results as they can identify the remote user even through firewalls etc,
			 * but are sometimes used in SQL injection attacks.
			 *
			 * We only want to take the first one we find, so search them in order and break when we find the first
			 * one.
			 *
			 */
			$envs = array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
			);

			foreach ( $envs as $env ) {
				$temp_ip = $this->get_ip_value(getenv($env));

				if ( false !== $temp_ip ) {
					$this->ip = $temp_ip;

					break;
				}
			}

			// If no valid ip address has been found, use 127.0.0.1 (aka localhost).
			if ( false === $this->ip ) {
				$this->ip = '127.0.0.1';
			}

			return $this->ip;
		}

		/**
		 * Validate an IPv6 IP address
		 *
		 * @param  string $ip
		 *
		 * @return boolean - true/false
		 */
		private function isValidIPv6( $ip ) {
			if ( false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {
				return false;
			} else {
				return true;
			}
		}

		// This function calls the user agent parsing code.
		public function get_UserAgent() {

			// Parse the agent stirng.
			try {
				$agent = parse_user_agent();
			} catch ( Exception $e ) {
				$agent = array(
					'browser'  => _x('Unknown', 'Browser', 'wp-statistics'),
					'platform' => _x('Unknown', 'Platform', 'wp-statistics'),
					'version'  => _x('Unknown', 'Version', 'wp-statistics'),
				);
			}

			// null isn't a very good default, so set it to Unknown instead.
			if ( $agent['browser'] == null ) {
				$agent['browser'] = _x('Unknown', 'Browser', 'wp-statistics');
			}
			if ( $agent['platform'] == null ) {
				$agent['platform'] = _x('Unknown', 'Platform', 'wp-statistics');
			}
			if ( $agent['version'] == null ) {
				$agent['version'] = _x('Unknown', 'Version', 'wp-statistics');
			}

			// Uncommon browsers often have some extra cruft, like brackets, http:// and other strings that we can strip out.
			$strip_strings = array( '"', "'", '(', ')', ';', ':', '/', '[', ']', '{', '}', 'http' );
			foreach ( $agent as $key => $value ) {
				$agent[ $key ] = str_replace($strip_strings, '', $agent[ $key ]);
			}

			return $agent;
		}

		// This function will return the referrer link for the current user.
		public function get_Referred( $default_referrer = false ) {

			if ( $this->referrer !== false ) {
				return $this->referrer;
			}

			$this->referrer = '';

			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$this->referrer = $_SERVER['HTTP_REFERER'];
			}
			if ( $default_referrer ) {
				$this->referrer = $default_referrer;
			}

			$this->referrer = esc_sql(strip_tags($this->referrer));

			if ( ! $this->referrer ) {
				$this->referrer = get_bloginfo('url');
			}

			if ( $this->get_option('addsearchwords', false) ) {
				// Check to see if this is a search engine referrer
				$SEInfo = $this->Search_Engine_Info($this->referrer);

				if ( is_array($SEInfo) ) {
					// If we're a known SE, check the query string
					if ( $SEInfo['tag'] != '' ) {
						$result = $this->Search_Engine_QueryString($this->referrer);

						// If there were no search words, let's add the page title
						if ( $result == '' || $result == 'No search query found!' ) {
							$result = wp_title('', false);
							if ( $result != '' ) {
								$this->referrer = esc_url(
									add_query_arg(
										$SEInfo['querykey'],
										urlencode('~"' . $result . '"'),
										$this->referrer
									)
								);
							}
						}
					}
				}
			}

			return $this->referrer;
		}

		// This function returns a date string in the desired format with a passed in timestamp.
		public function Local_Date( $format, $timestamp ) {
			return date($format, $timestamp + $this->tz_offset);
		}

		// This function returns a date string in the desired format.
		public function Current_Date( $format = 'Y-m-d H:i:s', $strtotime = null, $relative = null ) {

			if ( $strtotime ) {
				if ( $relative ) {
					return date($format, strtotime("{$strtotime} day", $relative) + $this->tz_offset);
				} else {
					return date($format, strtotime("{$strtotime} day") + $this->tz_offset);
				}
			} else {
				return date($format, time() + $this->tz_offset);
			}
		}

		// This function returns a date string in the desired format.
		public function Real_Current_Date( $format = 'Y-m-d H:i:s', $strtotime = null, $relative = null ) {

			if ( $strtotime ) {
				if ( $relative ) {
					return date($format, strtotime("{$strtotime} day", $relative));
				} else {
					return date($format, strtotime("{$strtotime} day"));
				}
			} else {
				return date($format, time());
			}
		}

		// This function returns an internationalized date string in the desired format.
		public function Current_Date_i18n( $format = 'Y-m-d H:i:s', $strtotime = null, $day = ' day' ) {

			if ( $strtotime ) {
				return date_i18n($format, strtotime("{$strtotime}{$day}") + $this->tz_offset);
			} else {
				return date_i18n($format, time() + $this->tz_offset);
			}
		}

		public function strtotimetz( $timestring ) {
			return strtotime($timestring) + $this->tz_offset;
		}

		public function timetz() {
			return time() + $this->tz_offset;
		}

		// This function checks to see if a search engine exists in the current list of search engines.
		public function Check_Search_Engines( $search_engine_name, $search_engine = null ) {

			if ( strstr($search_engine, $search_engine_name) ) {
				return 1;
			}
		}

		// This function returns an array of information about a given search engine based on the url passed in.
		// It is used in several places to get the SE icon or the sql query to select an individual SE from the database.
		public function Search_Engine_Info( $url = false ) {

			// If no URL was passed in, get the current referrer for the session.
			if ( ! $url ) {
				$url = isset( $_SERVER['HTTP_REFERER'] ) ? $this->get_Referred() : false;
			}

			// If there is no URL and no referrer, always return false.
			if ( $url == false ) {
				return false;
			}

			// Parse the URL in to it's component parts.
			$parts = parse_url($url);

			// Get the list of search engines we currently support.
			$search_engines = wp_statistics_searchengine_list();

			// Loop through the SE list until we find which search engine matches.
			foreach ( $search_engines as $key => $value ) {
				$search_regex = wp_statistics_searchengine_regex($key);

				preg_match('/' . $search_regex . '/', $parts['host'], $matches);

				if ( isset( $matches[1] ) ) {
					// Return the first matched SE.
					return $value;
				}
			}

			// If no SE matched, return some defaults.
			return array(
				'name'         => _x('Unknown', 'Search Engine', 'wp-statistics'),
				'tag'          => '',
				'sqlpattern'   => '',
				'regexpattern' => '',
				'querykey'     => 'q',
				'image'        => 'unknown.png',
			);
		}

		// This function returns an array of information about a given search engine based on the url passed in.
		// It is used in several places to get the SE icon or the sql query to select an individual SE from the database.
		public function Search_Engine_Info_By_Engine( $engine = false ) {

			// If there is no URL and no referrer, always return false.
			if ( $engine == false ) {
				return false;
			}

			// Get the list of search engines we currently support.
			$search_engines = wp_statistics_searchengine_list();

			if ( array_key_exists($engine, $search_engines) ) {
				return $search_engines[ $engine ];
			}

			// If no SE matched, return some defaults.
			return array(
				'name'         => _x('Unknown', 'Search Engine', 'wp-statistics'),
				'tag'          => '',
				'sqlpattern'   => '',
				'regexpattern' => '',
				'querykey'     => 'q',
				'image'        => 'unknown.png',
			);
		}

		// This function will parse a URL from a referrer and return the search query words used.
		public function Search_Engine_QueryString( $url = false ) {

			// If no URL was passed in, get the current referrer for the session.
			if ( ! $url ) {
				$url = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : false;
			}

			// If there is no URL and no referrer, always return false.
			if ( $url == false ) {
				return false;
			}

			// Parse the URL in to it's component parts.
			$parts = parse_url($url);

			// Check to see if there is a query component in the URL (everything after the ?).  If there isn't one
			// set an empty array so we don't get errors later.
			if ( array_key_exists('query', $parts) ) {
				parse_str($parts['query'], $query);
			} else {
				$query = array();
			}

			// Get the list of search engines we currently support.
			$search_engines = wp_statistics_searchengine_list();

			// Loop through the SE list until we find which search engine matches.
			foreach ( $search_engines as $key => $value ) {
				$search_regex = wp_statistics_searchengine_regex($key);

				preg_match('/' . $search_regex . '/', $parts['host'], $matches);

				if ( isset( $matches[1] ) ) {
					// Check to see if the query key the SE uses exists in the query part of the URL.
					if ( array_key_exists($search_engines[ $key ]['querykey'], $query) ) {
						$words = strip_tags($query[ $search_engines[ $key ]['querykey'] ]);
					} else {
						$words = '';
					}

					// If no words were found, return a pleasant default.
					if ( $words == '' ) {
						$words = 'No search query found!';
					}

					return $words;
				}
			}

			// We should never actually get to this point, but let's make sure we return something
			// just in case something goes terribly wrong.
			return 'No search query found!';
		}

		public function Get_Historical_Data( $type, $id = '' ) {

			$count = 0;

			switch ( $type ) {
				case 'visitors':
					if ( array_key_exists('visitors', $this->historical) ) {
						return $this->historical['visitors'];
					} else {
						$result
							= $this->db->get_var(
							"SELECT value FROM {$this->tb_prefix}statistics_historical WHERE category = 'visitors'"
						);
						if ( $result > $count ) {
							$count = $result;
						}
						$this->historical['visitors'] = $count;
					}

				break;
				case 'visits':
					if ( array_key_exists('visits', $this->historical) ) {
						return $this->historical['visits'];
					} else {
						$result
							= $this->db->get_var(
							"SELECT value FROM {$this->tb_prefix}statistics_historical WHERE category = 'visits'"
						);
						if ( $result > $count ) {
							$count = $result;
						}
						$this->historical['visits'] = $count;
					}

				break;
				case 'uri':
					if ( array_key_exists($id, $this->historical) ) {
						return $this->historical[ $id ];
					} else {
						$result
							= $this->db->get_var(
							$this->db->prepare(
								"SELECT value FROM {$this->tb_prefix}statistics_historical WHERE category = 'uri' AND uri = %s",
								$id
							)
						);
						if ( $result > $count ) {
							$count = $result;
						}
						$this->historical[ $id ] = $count;
					}

				break;
				case 'page':
					if ( array_key_exists($id, $this->historical) ) {
						return $this->historical[ $id ];
					} else {
						$result
							= $this->db->get_var(
							$this->db->prepare(
								"SELECT value FROM {$this->tb_prefix}statistics_historical WHERE category = 'uri' AND page_id = %d",
								$id
							)
						);
						if ( $result > $count ) {
							$count = $result;
						}
						$this->historical[ $id ] = $count;
					}

				break;
			}

			return $count;
		}

		public function feed_detected() {
			$this->is_feed = true;
		}

		public function check_feed() {
			return $this->is_feed;
		}

		public function get_country_codes() {
			if ( $this->country_codes == false ) {
				$ISOCountryCode = array();
				include( WP_Statistics::$reg['plugin-dir'] . "includes/functions/country-codes.php" );
				$this->country_codes = $ISOCountryCode;
			}

			return $this->country_codes;
		}

		// Returns an array of site id's
		public function get_wp_sites_list() {
			GLOBAL $wp_version;

			$site_list = array();

			// wp_get_sites() is deprecated in 4.6 or above and replaced with get_sites().
			if ( version_compare($wp_version, '4.6', '>=') ) {
				$sites = get_sites();

				foreach ( $sites as $site ) {
					$site_list[] = $site->blog_id;
				}
			} else {
				$sites = wp_get_sites();

				foreach ( $sites as $site ) {
					$site_list[] = $site['blog_id'];
				}
			}

			return $site_list;
		}

		public function html_sanitize_referrer( $referrer, $length = -1 ) {
			$referrer = trim($referrer);

			if ( 'data:' == strtolower(substr($referrer, 0, 5)) ) {
				$referrer = 'http://127.0.0.1';
			}

			if ( 'javascript:' == strtolower(substr($referrer, 0, 11)) ) {
				$referrer = 'http://127.0.0.1';
			}

			if ( $length > 0 ) {
				$referrer = substr($referrer, 0, $length);
			}

			return htmlentities($referrer, ENT_QUOTES);
		}

		public function get_referrer_link( $referrer, $length = -1 ) {
			$html_referrer = $this->html_sanitize_referrer($referrer);

			if ( $length > 0 && strlen($referrer) > $length ) {
				$html_referrer_limited = $this->html_sanitize_referrer($referrer, $length);
				$eplises               = '[...]';
			} else {
				$html_referrer_limited = $html_referrer;
				$eplises               = '';
			}

			return "<a href='{$html_referrer}'><div class='dashicons dashicons-admin-links'></div>{$html_referrer_limited}{$eplises}</a>";
		}

	}
}
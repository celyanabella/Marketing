<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'administrador');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'uGi0w_8>d4`w`I7F9*]KJ>?+$~{8f8@V&Y*RwOHGKy[$,6?Tzj[Qk{>O.g-z,5jv');
define('SECURE_AUTH_KEY',  '[-k6MJtIN `:#8.Bz5)Ca.!ditP0`> )S#(pePB$HlHU5t[b3G;t|2~$pc,AFpB,');
define('LOGGED_IN_KEY',    'Kx7yxN_`^?AT._2{&8isEqQV9A@FX4#UwPrT,qWrqI}i`N1XP`>>:op6G`>$>?@-');
define('NONCE_KEY',        'u|aT*MnhD.8e+a[V5~:V=4BZ{7pd>DnsoMW@}[@63y@c(Z gYhw=V[py6S%{r1]h');
define('AUTH_SALT',        '=mr;jIO EOe~y[B9NUShk}WTt!v6>sET$&B*q7U*}X`LN-W:S1cj|J S(Je[I5CM');
define('SECURE_AUTH_SALT', 'lU0FArWY5<~fk4!J990jR{o/{$qeiEH+5^wNhgJJEsNSE1<w|?hn7(5E{:ig1S=l');
define('LOGGED_IN_SALT',   'kqr#<O~(Wj!M@L_m+}1f#cRXkOs7jhZh!&}Z|SNi5s)|eFp(9k> -2B6E6)W7bva');
define('NONCE_SALT',       'nyP|1N*f,LgK/-XIULB..yXVWVJbt+PZ}0%tBSk-09QqARI#{U;nf+bE;w;h?0Dt');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

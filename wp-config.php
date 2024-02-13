<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'lovely_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'whykn0t?zero' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'UV-owfo5lVCu8BU9olaNk0QDzlVfSs]|;,x/CdgO3rY7-JId|%6d?$65POxy{iAq' );
define( 'SECURE_AUTH_KEY',  ')iv&/[e.M)BtZ-RO c/0(/[ErX`nkd`JZg)=cFo$$EPYE_z4cPrD=xx%x?JfA7u]' );
define( 'LOGGED_IN_KEY',    'dqmTIrP-?5Cd*!LMyPRBUZ%r/H7sEn,/N+#oj&V#(5Kd.R};[IcQ:%mO:wL+.X!2' );
define( 'NONCE_KEY',        '[A^)E69O@T7}z&`OqUVa@mMm+RrJ!,K~{#o7InwrY#/PR=XsUXF[;:D:b^lc6.vD' );
define( 'AUTH_SALT',        'btm,,U[%}iVKQXh-0hcS;*1(_Ou?{{o7;!Q2PT^SPErtP[R2dvZ,`k>wRkd3Asu)' );
define( 'SECURE_AUTH_SALT', '>i&,XS?@D7:m[xF;J,Yhi/i_c&Ff5Gb6]! #UHbt2[FsDLaMsBnHr#J+[EA^4PtU' );
define( 'LOGGED_IN_SALT',   '*!L%8$o]d+.{D$q(32@i_77(_Y@G&WO@R/-LC`&bUUWe0J:V&(NQE>xoh%e92*XZ' );
define( 'NONCE_SALT',       'YTulo>C?+G|;WU_OVxc |~fnP]}tzcj$B)_5.KcT:.1JAq(Y8MTVI`Xh(M$qLxPI' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

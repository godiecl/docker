<?php

define('WP_DEBUG', true);

// sqlite rulez!
define('DATABASE_TYPE', 'sqlite');
define('DB_DIR',         '/app/wp-content/database');
define('DB_FILE',        'db.sqlite');
define('DB_NAME',        'wordpress');
define('FS_METHOD',      'direct');

define('AUTH_KEY',         'b(Ft7f1{4vZ(!(#w +QOW~LJk!Ta{W*Pd(E%;xm$6!AW!?fNm^qk7NoN4}DJz%<6');
define('SECURE_AUTH_KEY',  'AB+hbE>Dp$z`Fi*[mf9SI-M~VSG8w@:CBJQoewKye.W.ved@Il&|nBlv(]RjK-N6');
define('LOGGED_IN_KEY',    '.SGujBpVy8bO648B^t=OL]/o!-{2sa<}K<m7+pAp|f3--KW2^}FzR7bT-|+6zXsM');
define('NONCE_KEY',        '-M.J%4&:yWZXb(lq]wo--1;Ws-:pQ|PGA5uXfh|=?i$=n=P5JVp%v7PYmJ9bbywQ');
define('AUTH_SALT',        'w$]&^ZPHc%Y+PX:-!gHU8v2&L%=Gc4&l]lH7nHvgv}>5;pzCcS,D|U[)}@Fhj{@M');
define('SECURE_AUTH_SALT', 'U86ym*|t5h#^y>!9hK:ZIGr+7K_|`=U{lTbx-T#1(1laDNYU7-.snwckgZb]A3| ');
define('LOGGED_IN_SALT',   'QUBf3O8Z+,(TK2t1h|ua|,{Zb|^#/D|:qY]x0>-?|&X>Mg:9qia&TN]WOV;][q3/');
define('NONCE_SALT',       ']_fg{w<|>6N|-T-|l|;WL+NGZ3fqL3>>9+eA9BM+RK?}v{)-*~6!_oWc9cj12WcJ');

$table_prefix = 'wp_';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

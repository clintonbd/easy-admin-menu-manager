<?php
/**
 * Plugin Name: Easy Admin Menu Manager
 * Plugin URI:  https://creatorseo.com/
 * Description: Easily Remove the clutter from your admin menu without losing control
 * Version:     1.0.1
 * Author:		Clinton [CreatorSEO]
 * Author URI:  http://www.creatorseo.com
 * License:     GPLv3
 * Last change: 2022-01-13
 *
 * Copyright 2022 CreatorSEO (email : info@creatorseo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You can find a copy of the GNU General Public License at the link
 * http://www.gnu.org/licenses/gpl.html or write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

//Security - abort if this file is called directly
if (!defined('WPINC')){
	die;
}

//error_reporting(E_ALL);
define( 'EAMM_ROOT', __FILE__ );
define( 'EAMM_DIR', plugin_dir_path( __FILE__ ) );
//require_once( HUB_DICAP_DIR . 'inc/creator-function-lib.php');
require_once( EAMM_DIR . 'class.easy-admin-menu.php');

$pgf = new easy_admin_menu(__FILE__);
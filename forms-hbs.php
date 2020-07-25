<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by KCMS to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/kubeecms/forms-hbs/
 * @since             1.3
 * @package           forms-hbs
 *
 * @wordpress-plugin
 * Plugin Name: Forms Hbs
 * Plugin URI: https://github.com/KubeeCMS/Forms-Hbs/
 * Description: Integrates Forms with HubSpot, allowing form submissions to be automatically sent to HubSpot.
 * Version: 1.3
 * Author: Kubee CMS
 * Author URI: https://github.com/KubeeCMS/
 * License: GNU GENERAL PUBLIC LICENSE
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gravityformshubspot
 * Domain Path:       /languages
 */



/*
------------------------------------------------------------------------
Copyright 2019-2020 Kubee, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.

*/


// Defines the current version of the Gravity Forms HubSpot Add-On.
define( 'GF_HSPOT_VERSION', '1.3' );

define( 'GF_HSPOT_MIN_GF_VERSION', '2.2' );

// After GF is loaded, load the add-on.
add_action( 'gform_loaded', array( 'GF_HSpot_Bootstrap', 'load_addon' ), 5 );


/**
 * Loads the Gravity Forms HubSpot Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class GF_HSpot_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since 1.0
	 * @access public
	 * @static
	 */
	public static function load_addon() {

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . '/class-gf-hubspot.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_HubSpot' );
	}
}

/**
 * Returns an instance of the GF_HubSpot class
 *
 * @since 1.0
 * @return GF_HubSpot An instance of the GF_HubSpot class
 */
function gf_hspot() {
	return GF_HubSpot::get_instance();
}

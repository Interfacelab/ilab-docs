<?php
/*
Plugin Name: WP Help Docs
Plugin URI: https://github.com/Interfacelab/ilab-docs
Description: Directly integrate markdown based help documentation for your WordPress theme or plugin into the WordPress admin for your end users and clients.
Author: interfacelab
Version: 1.0.3
Author URI: http://interfacelab.io
*/

// Copyright (c) 2016 Interfacelab LLC. All rights reserved.
//
// Released under the GPLv3 license
// http://www.gnu.org/licenses/gpl-3.0.html
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

define('ILAB_DOCS_DIR',dirname(__FILE__));
define('ILAB_DOCS_VENDOR_DIR',ILAB_DOCS_DIR.'/vendor');

// Composer
if (file_exists(ILAB_DOCS_VENDOR_DIR.'/autoload.php')) {
    require_once(ILAB_DOCS_VENDOR_DIR.'/autoload.php');
}

if (is_admin()) {
    $plug_url = plugin_dir_url( __FILE__ );
    define('ILAB_DOCS_PUB_CSS_URL',$plug_url.'public/css');
    define('ILAB_DOCS_PUB_JS_URL',$plug_url.'public/js');

    add_action('plugins_loaded', function(){
        new \ILAB\Docs\Plugin\DocsPlugin();
    });
}

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
    \ILAB\Docs\CLI\Search\SearchCommands::Register();
}


<?php
/*
Plugin Name: iLab Docs
Plugin URI: http://interfacelab.com/ilab-docs
Description: Include theme documentation into the WordPress admin
Author: interfacelab
Version: 1.0.0
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


require_once('vendor/autoload.php');
require_once('classes/ILabDocsPlugin.php');

if (is_admin()) {
    $plug_url = plugin_dir_url( __FILE__ );
    define('ILAB_DOCS_PUB_CSS_URL',$plug_url.'public/css');
    define('ILAB_DOCS_PUB_JS_URL',$plug_url.'public/js');

    new ILabDocsPlugin();
}

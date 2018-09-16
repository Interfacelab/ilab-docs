<?php
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

namespace ILAB\Docs\CLI\Search;

use ILAB\Docs\CLI\Command;
use TeamTNT\TNTSearch\TNTSearch;

if (!defined('ABSPATH')) { header('Location: /'); die; }

/**
 * Creates the search index
 * @package ILAB\Docs\CLI\Search
 */
class SearchCommands extends Command {
    /**
     * Builds the search index for your documentation
     *
     * @when after_wp_load
     *
     * @param $args
     * @param $assoc_args
     */
    public function index($args, $assoc_args) {
        $docsDirectory = get_template_directory().'/docs/';
        $docsDirectory = apply_filters('ilab-docs-directory', $docsDirectory);

        $tnt = new TNTSearch();
        $tnt->loadConfig([
            "driver"    => 'filesystem',
            "location"  => $docsDirectory,
            "extension" => "md",
            'storage'   => $docsDirectory
        ]);

        $indexer = $tnt->createIndex('docs.index');
        $indexer->run();
    }

    public static function Register() {
        \WP_CLI::add_command('docs-search', __CLASS__);
    }

}
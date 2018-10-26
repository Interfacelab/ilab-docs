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
        $docsDirectory = getcwd().DIRECTORY_SEPARATOR;

        // Make sure the index file exists
        if (!file_exists($docsDirectory.'index.md')) {
            Command::Error('Missing index.md file.  This command should be run from your docs directory.');
            return;
        }

        // Make sure the config exists
        if (!file_exists($docsDirectory.'config.json')) {
            Command::Error('Missing config.json file.  This command should be run from your docs directory.');
            return;
        }

        if (file_exists($docsDirectory.'docs.index')) {
            unlink($docsDirectory.'docs.index');
        }

        $tnt = new TNTSearch();
        $tnt->loadConfig([
            "driver"    => 'filesystem',
            "location"  => $docsDirectory,
            "extension" => "md",
            'storage'   => $docsDirectory
        ]);

        $indexer = $tnt->createIndex('docs.index');
        $indexer->run();

        $sqlite = new \SQLite3($docsDirectory.DIRECTORY_SEPARATOR.'docs.index');
        $sqlite->query("INSERT INTO info (key, value) VALUES ('source_dir', '$docsDirectory')");
        $sqlite->close();
    }

    public static function Register() {
        \WP_CLI::add_command('docs', __CLASS__);
    }

}
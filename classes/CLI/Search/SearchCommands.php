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
    private function extractTitle($markdownFile) {
        return null;
    }

    private function structureToTOC($docsDirectory, $structure, &$toc) {
        $files = [];
        $children = [];
        foreach($structure as $key => $value) {
            if (is_array($value)) {
                $children[$key] = $value;
            } else {
                $files[] = $value;
            }
        }

        foreach($files as $file) {
            $title = $this->extractTitle($docsDirectory.$file) ?? 'Untitled';
            $src = str_replace('.md', '', $file);
            $entry = [
                'title' => $title,
                'src' => $src
            ];

            if (isset($children[$src])) {
                $entry['children'] = [];
                $this->structureToTOC($docsDirectory.$src.DIRECTORY_SEPARATOR, $children[$src], $entry['children']);
            }

            $toc[] = (object)$entry;
        }
    }


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

    /**
     * Builds the table of contents for your documentation
     *
     * @param $args
     * @param $assoc_args
     */
    public function toc($args, $assoc_args) {
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

        $config = json_decode(file_get_contents($docsDirectory.'config.json'), true);

        $dir_iterator = new \RecursiveDirectoryIterator($docsDirectory);
        $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
// could use CHILD_FIRST if you so wish

        $structure = [];
        foreach ($iterator as $file) {
            if (strpos($file,'.md') > 0) {
                $file = str_replace($docsDirectory, '', $file);
                $parts = explode(DIRECTORY_SEPARATOR, $file);
                $file = array_pop($parts);

                $current = &$structure;
                while(true) {
                    if (count($parts) == 0) {
                        $current[] = $file;
                        break;
                    }

                    $top = array_shift($parts);
                    if (isset($current[$top])) {
                        $current = &$current[$top];
                    } else {
                        $current[$top] = [];
                        $current = &$current[$top];
                    }
                }
            }
        }

        $toc = [];
        $this->structureToTOC($docsDirectory, $structure, $toc);

        print_r($toc);


    }

    public static function Register() {
        \WP_CLI::add_command('docs', __CLASS__);
    }

}
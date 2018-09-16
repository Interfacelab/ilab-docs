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

namespace ILAB\Docs\Plugin;

use TeamTNT\TNTSearch\TNTSearch;

if (!defined('ABSPATH')) { header('Location: /'); die; }

class DocsPlugin {
    private $docsURL;
    private $docsDirectory;
    private $currentPage;
    private $currentPagePath;
    private $config = [];

    private $canSearch;
    private $searchIndex;

    public function __construct() {
        $this->docsDirectory = get_template_directory().'/docs/';
        $this->docsDirectory = apply_filters('ilab-docs-directory', $this->docsDirectory);

        $this->docsURL = get_template_directory_uri().'/docs/';
        $this->docsURL = apply_filters('ilab-docs-url', $this->docsURL);

        $this->searchIndex = $this->docsDirectory.'docs.index';
        $this->canSearch = (file_exists($this->searchIndex) && extension_loaded('sqlite3'));

        // If no docs directory, bail
        if (!file_exists($this->docsDirectory)) {
            return;
        }

        // Get the currently requested page
        $this->currentPage = 'index';
        if (isset($_GET['doc-page'])) {
            $this->currentPage = $_GET['doc-page'];
        } else if (isset($_POST['doc-page'])) {
            $this->currentPage = $_POST['doc-page'];
        };

        $this->currentPagePath = realpath($this->docsDirectory.$this->currentPage.'.md');

        // Make sure the current file exists WITHIN the docs directory and that current page file exists
        if ((strpos($this->currentPagePath, $this->docsDirectory) !== 0) || !file_exists($this->currentPagePath)) {
            return;
        }

        $configFile = $this->docsDirectory.'config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        }

        add_action('admin_menu', [$this, 'buildMenu']);
        add_action('admin_bar_menu', [$this, 'buildAdminBarMenu'], 100);

        add_action('admin_enqueue_scripts', function(){
            wp_enqueue_script('ilab-docs-js', ILAB_DOCS_PUB_JS_URL.'/docs.js');
            wp_enqueue_style('ilab-docs-css', ILAB_DOCS_PUB_CSS_URL . '/docs.css' );
        });

        add_action('wp_ajax_ilab_render_doc_page', [$this,'displayAjaxPage']);

    }

    //region Rendering

    private function searchForCurrentPage($entries, &$results) {
        foreach($entries as $entry) {
            if ($entry['src'] == $this->currentPage) {
                $results[] = [
                    'title' => $entry['title'],
                    'src' => $entry['src']
                ];

                return true;
            }

            if (isset($entry['children'])) {
                if ($this->searchForCurrentPage($entry['children'],$results)) {
                    $results[] = [
                        'title' => $entry['title'],
                        'src' => $entry['src']
                    ];

                    return true;
                }
            }
        }

        return false;
    }

    private function getTrailForCurrentPage() {
        $title = isset($this->config['title']) ? $this->config['title'] : 'Documentation';

        $result = [
            [
                'title' => $title,
                'src' => 'index'
            ]
        ];

        if ($this->currentPage == 'index') {
            return $result;
        }

        $searchResults = [];
        $this->searchForCurrentPage($this->config['toc'], $searchResults);
        $searchResults = array_reverse($searchResults);

        return array_merge($result, $searchResults);
    }

    public function renderBreadcrumbs() {
        if (!isset($this->config['toc'])) {
            return '';
        }

        $trailResults = $this->getTrailForCurrentPage();

        $result = '<div class="ilab-docs-breadcrumbs"><ul>';
        for($i = 0; $i < count($trailResults); $i++) {
            if ($i == count($trailResults) - 1) {
                $result .= "<li>{$trailResults[$i]['title']}</li>";
            } else {
                $result .= "<li><a href='".admin_url('admin.php?page=ilab-docs-menu&doc-page='.$trailResults[$i]['src'])."'>{$trailResults[$i]['title']}</a></li>";
            }
        }
        $result .= '</ul></div>';

        return $result;
    }

    public function renderHeader() {
        $searchText = (isset($_POST['search-text'])) ? $_POST['search-text'] : null;

        $result = "<div class='ilab-docs-header".(($this->canSearch) ? ' ilab-docs-has-search' : '')."'>";
        if (isset($this->config['logo'])) {
            $title = isset($this->config['title']) ? $this->config['title'] : 'Documentation';
            $logoSrc = get_template_directory_uri().'/'.$this->config['logo']['src'];
            $logoWidth = $this->config['logo']['width'];
            $logoHeight = $this->config['logo']['height'];

            $result .= "<img src='$logoSrc' width='$logoWidth' height='$logoHeight'><span>$title</span>";
        } else {
            $result .= "";
        }

        if ($this->canSearch) {
            $result .= "<div class='ilab-docs-search'><form method='POST'><input type='hidden' action='docs-search'><input type='search' class='newtag form-input-tip ui-autocomplete-input' name='search-text' ".(($searchText) ? " value='$searchText'" : "")." placeholder='Search ...'><input type='submit' value='Search' class='button-primary'></form></div>";
        }

        $result .= "</div>";

        return $result;
    }

    public function renderPage() {
        $result = '';

        $text = file_get_contents($this->currentPagePath);

        $parser = new \Michelf\MarkdownExtra();
        $parser->url_filter_func = function($url) {
            // other doc links
            if (preg_match("/.*\.md/", $url)) {
                $url = str_replace('.md', '', $url);
                return admin_url('admin.php?page=ilab-docs-menu')."&doc-page=$url";
            }

            // admin links
            if (preg_match("/admin:(.*)/", $url, $matches)) {
                return admin_url($matches[1]);
            }

            // images local to the doc
            $matches = [];
            if (preg_match("/(^[^\/]{1}.*\.(?:jpg|png|jpeg|svg))/", $url, $matches)) {
                if (strpos($url, 'http://') === 0) {
                    return $url;
                }

                if (strpos($url, 'https://') === 0) {
                    return $url;
                }

                return $this->docsURL.$url;
            }

            return $url;
        };

        // Process embeds
        $embeds = [];
        if (preg_match_all("/@\(([^)]*)\)/", $text, $embeds)) {
            for($i = 0; $i < count($embeds[1]); $i++) {
                $embedCode = wp_oembed_get($embeds[1][$i]);
                $text = str_replace($embeds[0][$i], "<div class='embed-container'>$embedCode</div>", $text);
            }
        }

        // Convert to HTML
        $html = $parser->transform($text);

        $result .= $this->renderHeader();
        $result .= $this->renderBreadcrumbs();


        $result .= "<div class='ilab-docs-container'><div class='ilab-docs-body'>$html</div></div>";

        return $result;
    }

    private function getTocEntryForFile($entries, $fileName) {
        foreach($entries as $entry) {
            if ($entry['src'] == $fileName) {
                return $entry;
            }

            if (isset($entry['children'])) {
                $result = $this->getTocEntryForFile($entry['children'], $fileName);
                if ($result) {
                    return $result;
                }
            }
        }

        return false;
    }

    public function renderSearchResults() {
        $searchText = $_POST['search-text'];

        $tnt = new TNTSearch();
        $tnt->loadConfig([
            "driver"    => 'filesystem',
            "location"  => $this->docsDirectory,
            "extension" => "md",
            'storage'   => $this->docsDirectory
        ]);

        $tnt->selectIndex('docs.index');
        $searchResults = $tnt->search($searchText);

        $entries = [];
        foreach($searchResults as $searchResult) {
            $file = str_replace('.md', '', str_replace($this->docsDirectory, '', $searchResult['path']));
            $entry = $this->getTocEntryForFile($this->config['toc'], $file);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        $html = "<h2>Searched for '$searchText' and found ".count($entries)." results.</h2>\n";
        $html .= '<ul class="search-results">';
        foreach($entries as $entry) {
            $entryURL = admin_url('admin.php?page=ilab-docs-menu&doc-page='.$entry['src']);
            $html .= "<li><a href='$entryURL'>{$entry['title']}</li>";
        }
        $html .= '</ul>';

        $result = $this->renderHeader();
        $result .= "<div class='ilab-docs-container'><div class='ilab-docs-body'>$html</div></div>";

        return $result;
    }

    public function renderMenuPage() {
        if ($this->canSearch && isset($_POST['search-text'])) {
            echo $this->renderSearchResults();
        } else {
            echo $this->renderPage();
        }
    }

    public function displayAjaxPage() {
        if ($this->canSearch && isset($_POST['search-text'])) {
            $page = $this->renderSearchResults();
        } else {
            $page = $this->renderPage();
        }

        status_header( 200 );
        header( 'Content-type: application/json; charset=UTF-8' );
        echo json_encode(['html' => $page], JSON_PRETTY_PRINT);
        die;
    }

    //endregion


    //region Menu related

    private function buildAdminBarMenuEntries(\WP_Admin_Bar $wp_admin_bar, $parentID, $entries) {
        foreach($entries as $entry) {
            $entryNodeId = str_replace('/','-', 'ilab-docs-node-'.$entry['src']);
            $wp_admin_bar->add_node([
                'id' => $entryNodeId,
                'parent' => $parentID,
                'title' => $entry['title'],
                'href' => admin_url('admin.php?page=ilab-docs-menu&doc-page='.$entry['src']),
                'meta' => [
                    'class' => 'ilab-docs-link'
                ]
            ]);

            if (isset($entry['children'])) {
                $this->buildAdminBarMenuEntries($wp_admin_bar, $entryNodeId, $entry['children']);
            }
        }
    }

    public function buildAdminBarMenu(\WP_Admin_Bar $wp_admin_bar) {
        if (!isset($this->config['toc'])) {
            return;
        }

        $title = isset($this->config['toolbar']) ? $this->config['toolbar'] : 'Documentation';


        $wp_admin_bar->add_menu([
            'id' => 'ilab-docs-bar-menu',
            'title' => '<span class="ab-icon dashicons-editor-help"></span>'.$title
        ]);

        $this->buildAdminBarMenuEntries($wp_admin_bar, 'ilab-docs-bar-menu', $this->config['toc']);
    }

    public function buildMenu() {
        $title = isset($this->config['title']) ? $this->config['title'] : 'Documentation';
        $menu = isset($this->config['menu']) ? $this->config['menu'] : 'Documentation';

        add_menu_page($title, $menu, 'read', 'ilab-docs-menu', [$this,'renderMenuPage'],'dashicons-editor-help');

        if (isset($this->config['toc'])) {
            foreach($this->config['toc'] as $entry) {
                if ($entry['src'] == 'index') {
                    continue;
                }

                add_submenu_page('ilab-docs-menu', $entry['title'], $entry['title'], 'read','ilab-docs-menu&doc-page='.$entry['src'],'__return_null');
            }


        }

    }

    //endregion
}
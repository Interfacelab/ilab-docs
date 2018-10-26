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

use ILAB\Docs\Markdown\DocsMarkdownExtra;
use TeamTNT\TNTSearch\TNTSearch;

if (!defined('ABSPATH')) { header('Location: /'); die; }

/**
 * This plugin enables displaying markdown documentation to end users of the WordPress admin.  It supports
 * multiple docsets, searching, etc.
 *
 * @package ILAB\Docs\Plugin
 */
class DocsPlugin {
    /** @var array Configuration data for all of the installed docsets */
    private $docsConfig = [];

    /** @var null|string The current docset  */
    private $currentDocs = null;

    /** @var object|null The current docset configuration */
    private $currentConfig;

    /** @var string|null The current page identifier */
    private $currentPage;

    /** @var string|null The current filesystem path for the current page */
    private $currentPagePath;

    /**
     * DocsPlugin constructor.
     */
    public function __construct() {
        $docsConfig = [];

        $themeDocsDirectory = get_template_directory().'/docs/';
        if (file_exists($themeDocsDirectory)) {
            $docsConfig['theme'] = [
                'dir' => $themeDocsDirectory,
                'url' => get_template_directory_uri().'/docs/'
            ];
        }

        $docsConfig = apply_filters('ilab-docs-config', $docsConfig);
        foreach($docsConfig as $key => $config) {
            $config['dir'] = DIRECTORY_SEPARATOR.trim($config['dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $config['url'] = trim($config['url'], '/').'/';
            $searchIndex = $config['dir'].'docs.index';
            if (file_exists($searchIndex)) {
                $config['search'] = $searchIndex;
                $config['canSearch'] = extension_loaded('sqlite3');
            } else {
                $config['canSearch'] = false;
            }

            $config['config'] = [];
            if (file_exists($config['dir'].'config.json')) {
                $config['config'] = json_decode(file_get_contents($config['dir'].'config.json'), true);
            }

            $config['standalone'] = (isset($config['config']['standalone'])) ? $config['config']['standalone'] : false;

            $this->docsConfig[sanitize_title($key)] = (object)$config;
        }

        // If no docs directory, bail
        if (count($this->docsConfig) == 0) {
            return;
        }


        if (isset($_GET['page'])) {
            if (strpos($_GET['page'], 'ilab-docs-') === 0) {
                $this->currentDocs = str_replace('ilab-docs-', '', $_GET['page']);
            }
        }

        // Wasn't passed in from a menu, see if it's in the get parameters
        if ($this->currentDocs === null) {
            // Set the current docset to the first one for now
            if (isset($_GET['doc-set'])) {
                $this->currentDocs = $_GET['doc-set'];
            } else if (isset($_POST['doc-set'])) {
                $this->currentDocs = $_POST['doc-set'];
            }
        }

        // Final check for a doc set
        if (!isset($this->docsConfig[$this->currentDocs]) || ($this->currentDocs === null)) {
            $this->currentDocs = array_keys($this->docsConfig)[0];
        }

        // Load the current doc configuration
        $this->currentConfig = $this->docsConfig[$this->currentDocs];

        // Get the currently requested page
        $this->currentPage = 'index';
        if (isset($_GET['doc-page'])) {
            $this->currentPage = $_GET['doc-page'];
        } else if (isset($_POST['doc-page'])) {
            $this->currentPage = $_POST['doc-page'];
        };

        $this->currentPagePath = realpath($this->currentConfig->dir.$this->currentPage.'.md');

        // Make sure the current file exists WITHIN the docs directory and that current page file exists
        if ((strpos($this->currentPagePath, $this->currentConfig->dir) !== 0) || !file_exists($this->currentPagePath)) {
            return;
        }

        add_action('admin_menu', [$this, 'buildMenu']);
        add_action('admin_bar_menu', [$this, 'buildAdminBarMenu'], 100);

        add_action('admin_enqueue_scripts', function(){
            wp_enqueue_script('ilab-docs-js', ILAB_DOCS_PUB_JS_URL.'/docs.js');
            wp_enqueue_style('ilab-docs-css', ILAB_DOCS_PUB_CSS_URL . '/docs.css' );

            if (file_exists($this->currentConfig->dir.'docs.css')) {
                wp_enqueue_style('ilab-docs-css-'.$this->currentDocs, $this->currentConfig->url.'/docs.css' );
            }
        });

        add_action('wp_ajax_ilab_render_doc_page', [$this,'displayAjaxPage']);
    }

    //region TOC

    /**
     * Gets child TOC entries for the current page
     *
     * @param $page
     * @param $entries
     * @return array|null
     */
    private function getChildrenEntriesFor($page, $entries) {
        foreach($entries as $entry) {
            if ($entry['src'] == $page) {
                if (isset($entry['children'])) {
                    return $entry['children'];
                } else {
                    return [];
                }
            }

            if (isset($entry['children'])) {
                $res = $this->getChildrenEntriesFor($page, $entry['children']);
                if (is_array($res)) {
                    return $res;
                }
            }
        }

        return null;
    }

    /**
     * Searches the TOC for the current page
     *
     * @param $entries
     * @param $results
     * @return bool
     */
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

    /**
     * Returns the breadcrumb trail for the current page
     *
     * @return array
     */
    private function getTrailForCurrentPage() {
        $title = isset($this->currentConfig->config['title']) ? $this->currentConfig->config['title'] : 'Documentation';

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
        $this->searchForCurrentPage($this->currentConfig->config['toc'], $searchResults);
        $searchResults = array_reverse($searchResults);

        return array_merge($result, $searchResults);
    }

    /**
     * Returns the TOC entry for the given filename
     *
     * @param $entries
     * @param $fileName
     * @return bool
     */
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

    //endregion

    //region Rendering

    /**
     * Converts TOC entries to HTML
     * @param $entries
     * @return string
     */
    private function convertEntriesToHTML($entries) {
        $html = '';
        foreach($entries as $entry) {
            if ($entry['src'] == 'index') {
                continue;
            }

            $anchor = admin_url('admin.php?page=ilab-docs-'.$this->currentDocs.'&doc-page='.$entry['src']);
            $html .= "<li><a href='$anchor'>{$entry['title']}</a>";
            if (isset($entry['children'])) {
                $html .= '<ul>';
                $html .= $this->convertEntriesToHTML($entry['children']);
                $html .= '</ul>';
            }
            $html .= "</li>";
        }

        return $html;
    }

    /**
     * Renders the breadcrumbs for the current page
     *
     * @return string
     */
    private function renderBreadcrumbs() {
        if (!isset($this->currentConfig->config['toc'])) {
            return '';
        }

        $trailResults = $this->getTrailForCurrentPage();

        $result = '<div class="ilab-docs-breadcrumbs"><ul>';
        for($i = 0; $i < count($trailResults); $i++) {
            if ($i == count($trailResults) - 1) {
                $result .= "<li>{$trailResults[$i]['title']}</li>";
            } else {
                $result .= "<li><a href='".admin_url('admin.php?page=ilab-docs-'.$this->currentDocs.'&doc-page='.$trailResults[$i]['src'])."'>{$trailResults[$i]['title']}</a></li>";
            }
        }
        $result .= '</ul></div>';

        return $result;
    }

    /**
     * Renders the header
     *
     * @return string
     */
    private function renderHeader() {
        $searchText = (isset($_POST['search-text'])) ? $_POST['search-text'] : null;

        $result = "<div class='ilab-docs-header".(($this->currentConfig->canSearch) ? ' ilab-docs-has-search' : '')."'>";
        if (isset($this->currentConfig->config['logo'])) {
            $title = isset($this->currentConfig->config['title']) ? $this->currentConfig->config['title'] : 'Documentation';
            $logoSrc = $this->currentConfig->url.$this->currentConfig->config['logo']['src'];
            $logoWidth = $this->currentConfig->config['logo']['width'];
            $logoHeight = $this->currentConfig->config['logo']['height'];

            $result .= "<img src='$logoSrc' width='$logoWidth' height='$logoHeight'><span>$title</span>";
        } else {
            $result .= "";
        }

        if ($this->currentConfig->canSearch) {
            $result .= "<div class='ilab-docs-search'><form method='POST'><input type='hidden' name='action' value='docs-search'><input type='hidden' name='doc-set' value='{$this->currentDocs}'><input type='search' class='newtag form-input-tip ui-autocomplete-input' name='search-text' ".(($searchText) ? " value='$searchText'" : "")." placeholder='Search ...'><input type='submit' value='Search' class='button-primary'></form></div>";
        }

        $result .= "</div>";

        return $result;
    }

    /**
     * Renders the page
     *
     * @return string
     */
    private function renderPage() {
        $result = '';

        $text = file_get_contents($this->currentPagePath);

        $parser = new DocsMarkdownExtra();
        $parser->url_filter_func = function($url) {
            // other doc links
            if (preg_match("/.*\.md/", $url)) {
                $url = str_replace('.md', '', $url);
                return admin_url('admin.php?page=ilab-docs-'.$this->currentDocs."&doc-page=$url");
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

                return $this->currentConfig->url.$url;
            }

            return $url;
        };

        // Process embeds
        $embeds = [];
        if (preg_match_all("/\@\s*\[[^]]*\]\s*\(([^)]*)\)/m", $text, $embeds)) {
            for($i = 0; $i < count($embeds[1]); $i++) {
                $embedCode = wp_oembed_get($embeds[1][$i]);
                $isVideo = preg_match ("/\b(?:vimeo\.com|youtube\.com|youtu\.be|dailymotion\.com)\b/i", $embeds[1][$i]);
                $text = str_replace($embeds[0][$i], "<div class='embed-container".(($isVideo) ? ' embed-video':'')."'>$embedCode</div>", $text);
            }
        }

        // Process toc
        $tocs = [];
        if (preg_match_all("/@toc\(([^)]*)\)/m", $text, $tocs)) {
            for($i = 0; $i < count($tocs[1]); $i++) {
                $tocPage = $tocs[1][$i];
                if (empty($tocPage)) {
                    $tocPage = $this->currentPage;
                }

                if ($tocPage == 'index') {
                    $entries = $this->currentConfig->config['toc'];
                } else {
                    $entries = $this->getChildrenEntriesFor($tocPage, $this->currentConfig->config['toc']);
                }

                if (!empty($entries) && is_array($entries) && (count($entries) > 0)) {
                    $tocHTML = '<ul class="toc">';
                    $tocHTML .= $this->convertEntriesToHTML($entries);
                    $tocHTML .='</ul>';
                    $text = str_replace($tocs[0][$i], $tocHTML, $text);
                }
            }
        }

        // Convert to HTML
        $html = $parser->transform($text);

        $result .= $this->renderHeader();
        $result .= $this->renderBreadcrumbs();


        $result .= "<div class='ilab-docs-container'><div class='ilab-docs-body'>$html</div></div>";

        return $result;
    }

    /**
     * Renders the search results
     *
     * @return string
     * @throws \TeamTNT\TNTSearch\Exceptions\IndexNotFoundException
     * @throws \Exception
     */
    private function renderSearchResults() {
        $sqlite = new \SQLite3($this->currentConfig->dir.'docs.index');
        $res = $sqlite->query("select value from info where key = 'source_dir'")->fetchArray();
        if ($res === false) {
            return "<div class='ilab-docs-container'><div class='ilab-docs-body'>Search index is corrupt or missing.</div></div>";
        }

        $searchDir = $res[0];

        $searchText = $_POST['search-text'];

        $tnt = new TNTSearch();
        $tnt->loadConfig([
            "driver"    => 'filesystem',
            "location"  => $this->currentConfig->dir,
            "extension" => "md",
            'storage'   => $this->currentConfig->dir
        ]);

        $tnt->selectIndex('docs.index');
        $searchResults = $tnt->search($searchText);

        $entries = [];
        foreach($searchResults as $searchResult) {
            $file = str_replace('.md', '', str_replace($searchDir, '', $searchResult['path']));
            $entry = $this->getTocEntryForFile($this->currentConfig->config['toc'], $file);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        $html = "<h2>Searched for '$searchText' and found ".count($entries)." results.</h2>\n";
        $html .= '<ul class="search-results">';
        foreach($entries as $entry) {
            $entryURL = admin_url('admin.php?page=ilab-docs-'.$this->currentDocs.'&doc-page='.$entry['src']);
            $html .= "<li><a href='$entryURL'>{$entry['title']}</li>";
        }
        $html .= '</ul>';

        $result = $this->renderHeader();
        $result .= "<div class='ilab-docs-container'><div class='ilab-docs-body'>$html</div></div>";

        return $result;
    }

    /**
     * Renders the page and returns as an ajax json response
     */
    public function displayAjaxPage() {
        if ($this->currentConfig->canSearch && isset($_POST['search-text'])) {
            $page = $this->renderSearchResults();
        } else {
            $page = $this->renderPage();
        }

        status_header( 200 );
        header( 'Content-type: application/json; charset=UTF-8' );
        echo json_encode(['html' => $page], JSON_PRETTY_PRINT);
        die;
    }

    /**
     * Renders the entire page
     */
    public function renderMenuPage() {
        if ($this->currentConfig->canSearch && isset($_POST['search-text'])) {
            echo $this->renderSearchResults();
        } else {
            echo $this->renderPage();
        }
    }

    //endregion


    //region Admin Menu Bar

    /**
     * Builds Admin Bar child entries
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     * @param $parentID
     * @param $entries
     */
    private function buildAdminBarMenuEntries($docSet, \WP_Admin_Bar $wp_admin_bar, $parentID, $entries) {
        foreach($entries as $entry) {
            $entryNodeId = str_replace('/','-', 'ilab-docs-node-'.$docSet.'-'.$entry['src']);
            $wp_admin_bar->add_node([
                'id' => $entryNodeId,
                'parent' => $parentID,
                'title' => $entry['title'],
                'href' => admin_url('admin.php?page=ilab-docs-'.$docSet.'&doc-page='.$entry['src']),
                'meta' => [
                    'class' => 'ilab-docs-link'
                ]
            ]);

            if (isset($entry['children'])) {
                $this->buildAdminBarMenuEntries($docSet, $wp_admin_bar, $entryNodeId, $entry['children']);
            }
        }
    }

    /**
     * Builds the admin bar for a single docset
     * @param $key
     * @param $config
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    private function buildSingleAdminBarMenu($key, $config, \WP_Admin_Bar $wp_admin_bar) {
        $title = isset($config->config['toolbar']) ? $config->config['toolbar'] : $config->config['title'];
        $slug = 'ilab-docs-bar-'.$key;

        $wp_admin_bar->add_menu([
            'id' => $slug,
            'title' => '<span class="ab-icon dashicons-editor-help"></span>'.$title
        ]);

        $this->buildAdminBarMenuEntries($key, $wp_admin_bar, $slug, $config->config['toc']);
    }

    /**
     * Builds the admin bar for multiple docsets
     * @param $configs
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    private function buildPluginsAdminBarMenu($configs, \WP_Admin_Bar $wp_admin_bar) {
        $wp_admin_bar->add_menu([
            'id' => 'ilab-docs-bar-menu',
            'title' => '<span class="ab-icon dashicons-editor-help"></span>Plugin Docs'
        ]);

        foreach($configs as $key => $config) {
            $entryNodeId = str_replace('/','-', 'ilab-docs-node-'.$key);
            $wp_admin_bar->add_node([
                'id' => $entryNodeId,
                'parent' => 'ilab-docs-bar-menu',
                'title' => $config->config['toolbar'] ?: $config->config['title'],
                'href' => admin_url('admin.php?page=ilab-docs-'.$key.'&doc-page=index'),
                'meta' => [
                    'class' => 'ilab-docs-link'
                ]
            ]);

            if (isset($config->config['toc'])) {
                $this->buildAdminBarMenuEntries($key, $wp_admin_bar, $entryNodeId, $config->config['toc']);
            }
        }
    }

    /**
     * Builds the admin bar
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function buildAdminBarMenu(\WP_Admin_Bar $wp_admin_bar) {
        $menusToBuild = [];
        foreach($this->docsConfig as $key => $config) {
            if (($key == 'theme') || $config->standalone) {
                $menusToBuild[$key] = $config;
            } else {
                if (!isset($menusToBuild['plugins'])) {
                    $menusToBuild['plugins'] = [];
                }

                $menusToBuild['plugins'][$key] = $config;
            }
        }

        foreach($menusToBuild as $key => $config) {
            if ($key == 'plugins') {
                continue;
            }

            $this->buildSingleAdminBarMenu($key, $config, $wp_admin_bar);
        }

        if (isset($menusToBuild['plugins'])) {
            if (count($menusToBuild['plugins']) == 1) {
                $this->buildSingleAdminBarMenu(array_keys($menusToBuild['plugins'])[0], array_values($menusToBuild['plugins'])[0], $wp_admin_bar);
            } else {
                $this->buildPluginsAdminBarMenu($menusToBuild['plugins'], $wp_admin_bar);
            }
        }
    }

    //endregion

    //region Admin Menus

    /**
     * Builds a single admin menu
     * @param $key
     * @param $config
     */
    public function buildSingleMenu($key, $config) {
        $title = isset($config->config['title']) ? $config->config['title'] : 'Documentation';
        $menu = isset($config->config['menu']) ? $config->config['menu'] : 'Documentation';
        $slug = 'ilab-docs-'.$key;

        add_menu_page($title, $menu, 'read', $slug, [$this,'renderMenuPage'],'dashicons-editor-help');

        if (isset($config->config['toc'])) {
            foreach($config->config['toc'] as $entry) {
                if ($entry['src'] == 'index') {
                    continue;
                }

                add_submenu_page($slug, $entry['title'], $entry['title'], 'read',$slug.'&doc-page='.$entry['src'],[$this,'renderMenuPage']);
            }
        }
    }

    /**
     * Builds admin menu when there are multiple docsets
     */
    public function buildPluginsMenu($configs) {
        $firstKey = array_keys($configs)[0];

        add_menu_page('Plugin Docs', 'Plugin Docs', 'read', 'ilab-docs-'.$firstKey, [$this,'renderMenuPage'],'dashicons-editor-help');

        $first = true;
        foreach($configs as $key => $config) {
            if ($first) {
                add_submenu_page('ilab-docs-'.$firstKey, $config->config['title'], $config->config['title'], 'read','ilab-docs-'.$firstKey,[$this,'renderMenuPage']);
                $first = false;
            } else {
                add_submenu_page('ilab-docs-'.$firstKey, $config->config['title'], $config->config['title'], 'read','ilab-docs-'.$key, [$this,'renderMenuPage']);
            }
        }
    }

    /**
     * Builds the admin menu
     */
    public function buildMenu() {
        $menusToBuild = [];
        foreach($this->docsConfig as $key => $config) {
            if (($key == 'theme') || $config->standalone) {
                $menusToBuild[$key] = $config;
            } else {
                if (!isset($menusToBuild['plugins'])) {
                    $menusToBuild['plugins'] = [];
                }

                $menusToBuild['plugins'][$key] = $config;
            }
        }

        foreach($menusToBuild as $key => $config) {
            if ($key == 'plugins') {
                continue;
            }

            $this->buildSingleMenu($key, $config);
        }

        if (isset($menusToBuild['plugins'])) {
            if (count($menusToBuild['plugins']) == 1) {
                $this->buildSingleMenu(array_keys($menusToBuild['plugins'])[0], array_values($menusToBuild['plugins'])[0]);
            } else {
                $this->buildPluginsMenu($menusToBuild['plugins']);
            }
        }
    }

    //endregion
}
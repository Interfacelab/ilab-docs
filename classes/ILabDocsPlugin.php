<?php

class ILabDocsPlugin {
    private $docsURL;
    private $docsDirectory;
    private $currentPagePath;
    private $config = [];

    public function __construct() {
        $this->docsDirectory = get_template_directory().'/docs/';
        $this->docsDirectory = apply_filters('ilab-docs-directory', $this->docsDirectory);

        $this->docsURL = get_template_directory_uri().'/docs/';
        $this->docsURL = apply_filters('ilab-docs-url', $this->docsURL);

        // If no docs directory, bail
        if (!file_exists($this->docsDirectory)) {
            return;
        }

        // Get the currently requested page
        $currentPage = 'index';
        if (isset($_GET['doc-page'])) {
            $currentPage = $_GET['doc-page'];
        }

        $this->currentPagePath = realpath($this->docsDirectory.$currentPage.'.md');

        // Make sure the current file exists WITHIN the docs directory and that current page file exists
        if ((strpos($this->currentPagePath, $this->docsDirectory) !== 0) || !file_exists($this->currentPagePath)) {
            return;
        }

        $configFile = $this->docsDirectory.'config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        }

        add_action('admin_menu', [$this, 'buildMenu']);
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', [$this, 'menuOrder'], 100000);

        add_action('admin_enqueue_scripts', function(){
            wp_enqueue_script('ilab-docs-js', ILAB_DOCS_PUB_JS_URL.'/docs.js');
            wp_enqueue_style('ilab-docs-css', ILAB_DOCS_PUB_CSS_URL . '/docs.css' );
        });
    }

    //region Rendering

    public function renderPage() {
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

        if (isset($this->config['logo'])) {
            $title = isset($this->config['title']) ? $this->config['title'] : 'Documentation';
            $logoSrc = get_template_directory_uri().'/'.$this->config['logo']['src'];
            $logoWidth = $this->config['logo']['width'];
            $logoHeight = $this->config['logo']['height'];

            echo "<div class='docs-header'><img src='$logoSrc' width='$logoWidth' height='$logoHeight'><span>$title</span></div>";

        }


        echo "<div class='docs-container'><div class='docs-body'>$html</div></div>";
    }

    //endregion


    //region Menu related

    public function buildMenu() {
        $title = isset($this->config['title']) ? $this->config['title'] : 'Documentation';
        $menu = isset($this->config['menu']) ? $this->config['menu'] : 'Documentation';

        add_menu_page($title, $menu, 'read', 'ilab-docs-menu', [$this,'renderPage'],'dashicons-book');

        if (isset($this->config['subpages'])) {
            foreach($this->config['subpages'] as $subpage) {
                add_submenu_page('ilab-docs-menu', $subpage['title'], $subpage['title'], 'read','ilab-docs-menu&doc-page='.$subpage['src'],'__return_null');
            }
        }
    }

    public function menuOrder($menu) {
        for($i = 0; $i < count($menu); $i++) {
            if ($menu[$i] == 'ilab-docs-menu') {
                unset($menu[$i]);
                break;
            }
        }

        array_splice($menu, 2, 0, ['ilab-docs-menu', 'separator']);

        $separatorIndex = 1;
        $lastSeparatorIndex = -1;
        for($i = 0; $i < count($menu); $i++) {
            if (strpos($menu[$i], 'separator') === 0) {
                $menu[$i] = 'separator'.$separatorIndex;
                $separatorIndex++;
                $lastSeparatorIndex = $i;
            }
        }

        if ($lastSeparatorIndex >= 0) {
            $menu[$lastSeparatorIndex] = 'separator-last';
        }

        return $menu;
    }

    //endregion
}
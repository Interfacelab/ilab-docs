# WP Help Docs

The WP Help Docs plugin allows you to integrate help documentation for your WordPress theme or plugin directly into the WordPress admin.

Documentation is written in Markdown, with special extensions that allow linking to other markdown files, linking to admin pages, video embeds, displaying a table of contents and other features.  

WP Help Docs also has integrated search functionality.

For examples integrating this documentation plugin with your plugin or theme, check out these examples:

* [Example plugin with documentation](https://github.com/Interfacelab/ilab-docs-example-plugin)
* [Example theme with documentation](https://github.com/Interfacelab/ilab-docs-example-theme)

## Installation

Install via composer:

```bash
composer require ilab/ilab-docs
```

Or, install via the [WordPress plugin repository](https://wordpress.org/plugins/ilab-docs/).

Or, download the zip file from the [releases page](https://github.com/Interfacelab/ilab-docs/releases) and install manually.

## Writing Documentation

### Documentation Location
If you are writing documentation for your theme, you need to place your documentation in directory called `docs` in the root of your theme and the plugin will find it automatically.

Documentation for plugins, or for a directory other than `docs`, you will have to tell the docs plugin where to load it's documentation from.  You can do that through the `ilab-docs-config` filter:

```php
add_filter('ilab-docs-config', function($docsConfig){
	$docsConfig[] = [
		'title' => 'My Plugin',
		'dir' => dirname(__FILE__).'/docs/',
		'url' => plugin_dir_url( __FILE__ ).'docs/'
	];

    return $docsConfig;
}, 10000, 1);
```

This filter should be added to your plugin's main/entry PHP file or your theme's `functions.php`.

### Directory Structure
The basic directory structure for your `docs` folder should look like this:

```bash
images/
config.json
index.md
docs.css
```

The `images` folder contains any images included in your documentation.
The `config.json` holds the configuration and table of contents of your documentation.
`index.md` is the initial page of documentation.
`docs.css` is any additional CSS you'd like to include for your documentation.

In addition to this, you'll have additional markdown files for each page of documentation you wish to include.

### Configuration
Each document set requires a configuration file.  The configuration file is a simple JSON file that provides the title, name of the documentation to display in various menu areas, the logo and the table of contents.  

A basic configuration file looks like:

```json
{
  "title": "Documentation Title",
  "menu": "Documentation",
  "toolbar": "Docs",
  "logo": {
    "src": "images/doc-logo.svg",
    "width": 130,
    "height": 29
  },
  "toc": [
    {
      "title": "CMS Overview",
      "src": "index"
    },
    {
      "title": "Custom Content Types",
      "src": "content-types",
      "children": [
        {
          "title": "Managing Bios",
          "src": "content-types/bios"
        },
        {
          "title": "Managing Case Studies",
          "src": "content-types/case-studies"
        },
        {
          "title": "Managing News",
          "src": "content-types/news"
        }
      ]
    }
  ]
}
```

#### Basics
##### Title
This is the title of your documentation and is displayed in the documentation's header as well as the page's title.

##### Menu
This is the title of the documentation when it is displayed in the WordPress admin sidebar.

##### Logo
This is the logo used in the documentation itself.  It can be any valid image type and should be located in the `images/` folder of your `docs` directory.

##### Toolbar
This the name of the title when displayed in the WordPress admin toolbar.

##### Standalone
If you are writing plugin documentation, setting this flag to true will make your plugin documentation a top level menu item instead of being grouped in the *Plugin Docs* admin menus.  You shouldn't do this unless your plugin is a *must use* OR a major plugin with a lot of documentation.

Documentation for themes are always standalone top-level menu items.

#### Table of Contents
The table of contents is the complete list of all of the pages in your documentation.  Each element has a `title` attribute which is the title to display in the table of contents listing, and a `src` attribute which is the relative path to the markdown file (sans the markdown extension).

You can auto-generate the table of contents using *wp-cli*.  Simply open a terminal, navigate to your documentation directory and type:

```bash
wp docs toc
```

This will parse each markdown file in your documentation directory, and any subdirectories, and build the table of contents array for you.


### Markdown Extensions
The WP Help Docs plugin extends markdown in a few ways to facilitate writing cross-referencing documentation.

#### Linking
You can link to other pages of your documentation using standard markdown links:

```markdown
[Other Documentation](other-documentation.md)
```

The plugin will parse out these links and replace them with the appropriate ones that actually display the pages.

These links are **always** relative to your docs directory.


#### Admin Links
Sometimes you will want to link to some part of the WordPress admin.  You can do that using an `admin:` prefix like so:

```markdown
The menus on the site are editable via WordPress's built-in [menu editor](admin:nav-menus.php).
```

#### Local Images
Because your documentation is being displayed on pages that aren't directly related to where they are stored, regular markdown images won't work.  Instead, to include images that are relative to your documents directory, you would simply write it without a forward slash:

```markdown
![Image Name](images/image.png)
```

The plugin will automatically map this the image in your `images/` directory regardless of where the documentation is actually being displayed.

#### Video Embeds
For embedding video content, use the `@` symbol like so:

```markdown
@[Video Name](https://www.youtube.com/watch?v=dQw4w9WgXcQ)
```

Currently, videos from youtube, vimeo and dailymotion are supported.

#### Table Header Styles
You can specify CSS classes for table headers using `{}`:

```markdown
#### Content Properties
{callout} | Property{property} | Description{description}
----------|--------------------|-------------------------
![#1](images/icon-callout-1.png)|**Title**|The title of the callout panel.
![#2](images/icon-callout-2.png)|**Subtitle**|The subtitle of the callout panel.
![#3](images/icon-callout-3.png)|**Link**|The link to display in the callout's header, optional.  See [Link Properties](pages/content-blocks/link.md) for configuring links.
```

In the above example, the first column doesn't display any text but uses a css class called `callout`.  The Property column uses a css class called `property` and the Description column uses a css class called `description`.

This was added to give more control over table column widths.  For example, the actual css for these classes looks like:

```css
.ilab-docs-body .property {
    width: 84px;
    min-width: 84px;
}
.ilab-docs-body .description {
    width: 100%;
}

.ilab-docs-body .callout {
    width: 32px;
    min-width: 32px;
    max-width: 32px;
}
```

The css is included in the `docs.css` file in your `docs` directory.

#### Table of Contents
To display the table of contents in your documentation, simply use `@toc()`:

```markdown
Table of Contents
-----------------
@toc()
```

By default the `@toc()` will only include the the children toc entries at the current level of the documentation using the function.  If you want to include the entire table of contents for all of the documentation you would use: `@toc(index)`.

### Search
WP Help Docs supports documentation search through the `teamtnt/tntsearch` package.  But in order to enable search, you'll first have to generate a search index.  You can do this from the command line using **wp-cli**.  Open up a terminal and change to your documentation directory.  Type the following:

```bash
wp docs index
```

This will then generate a file called `docs.index` in the root of your documentation directory.  That is all you need to do to enable search!

**NOTE:** You will need to have the PHP SQLite3 extension installed for search to be functional.



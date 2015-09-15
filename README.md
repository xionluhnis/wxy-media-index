# wxy-media-index
Plugin for indexing any kind of media within wxy

## Configuration
The default configuration is:
```php
$config['media_index_ignore'] = array(
    '/^\./',                        // .file
    '/[#~]$/',                      // file# and file~
    '/404\\' . CONTENT_EXT . '/',   // 404.md
    '/.php$/',                      // file.php
    '/(plugins|themes)$/'
);
$config['media_index_accept'] = array();
```

The plugin provides a new ordering `type` for `$config['order_by']`:
```php
$config['index_type_order'] = array('directory', 'markdown', 'image', 'unknown', '');
```

## Template data
The index should enforce that the following data is generated
for any kind of data:

* `title` - the data title
* `url` - the data url
* `date` - the data date (either from metadata, or from file construction)
* `type` - the data type such as _image_, _markdown_, _directory_ or _unknown_

## Managing new content types
When loading data for a file, the plugin calls the following hook:

```php
indexing_content($media, $headers, &$data)
```

Plugins can process the data for new file types through this hook.
They must simply generate the data for the file `$media` and store
it in the array $data.

See the plugins:

* `media_index_directory.php` indexes directories
* `media_index_image.php` indexes images and provides meta data for these including the size
* `media_index_markdown.php` indexes markdown files and provides embedded meta data


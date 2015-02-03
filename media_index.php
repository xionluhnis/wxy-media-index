<?php

include_once ROOT_DIR . '/chrono.php';
include_once ROOT_DIR . '/files.php';
include_once ROOT_DIR . '/hooks.php';
include_once ROOT_DIR . '/markdown.php';

/**
 * Plugin that creates a media index instead of a simple page index.
 * It uses hooks to manage the content so that new media types can
 * be taken care by writing additional plugins.
 *
 * @author Alexandre Kaspar <xion.luhnis@gmail.com>
 * @version 0.1
 */
class Media_Index {

    private $ignore;
    private $accept;

    public function __construct() {
        $this->ignore = array(
            '/^\./',    // .file
            '/[#~]$/',  // file# and file~
            '/404\\' . CONTENT_EXT . '/',   // 404.md
            '/.php$/',  // file.php
            '/(plugins|themes)$/'
        );
        $this->accept = array();
    }

    /**
     * Create hook for content management plugins
     *
     * @param HookEnvironment $env the hook manager
     */
    public function plugins_loaded(&$env){
        $env->add_hook('indexing_content');
    }

    /**
     * Check for media configuration
     *
     * @param array &$config the settings
     */
    public function config_loaded(&$config){
        if(array_key_exists('media_index_ignore', $config)){
            $this->ignore = $config['media_index_ignore'];
        }
        if(array_key_exists('media_index_accept', $config)){
            $this->accept = $config['media_index_accept'];
        }
    }

    /**
     * Get a list of pages
     *
     * @param string file the file we get the content of
     * @param HookEnvironment $env
     * @return array $sorted_pages an array of pages
     */
    public function get_index($file, $env, $headers, &$sorted_medias)
    {
        global $config;
        $t = tictoc('plugin::get_index:');

        // clean file
        $file = Text::clean_slashes($file);

        $cur_file = basename($file);
        if($cur_file == 'index.md'){
            $cur_dir = dirname($file);
            $is_index = TRUE;
        } else {
            $cur_dir = str_replace('.md', '', $file);
            $is_index = FALSE;
        }
        $base_url = Request::base_url();
        $dir_route = Request::route();
        if(substr($dir_route, -1) != '/'){
            $dir_route = $base_url . dirname(str_replace($base_url, '', $dir_route));
        }
        $dir_route = trim($dir_route, '/');

        $medias = Files::find($cur_dir, '', FALSE, TRUE); // every file, single level
        $t->toc('find');

        $sorted_medias = array();
        $date_id = 0;
        foreach ($medias as $key => $media) {
            $t->toc('start ' . $media);
            // should we accept it without looking at ignore values?
            $ok = FALSE;
            foreach($this->accept as $acc) {
                if(preg_match($acc, $media)){
                    $ok = TRUE;
                    break;
                }
            }
            if(!$ok){
                $ok = TRUE;
                foreach($this->ignore as $ign) {
                    if(preg_match($ign, $media)){
                        $ok = FALSE;
                        break;
                    }
                }
                if(!$ok){
                    // we ignore it
                    unset($medias[$key]);
                    $t->toc('ignore');
                    continue;
                }
            }

            // load index data
            $data = array();
            $env->run_hooks('indexing_content', array($media, $headers, &$data));
            $t->toc('content_hooks');
            if(empty($data)){
                $data = self::get_default_content($media);
                $t->toc('content_default');
            }

            // force parameters
            $data['file'] = $media;
            $data['filename'] = pathinfo($media, PATHINFO_BASENAME);
            $data['extension'] = pathinfo($media, PATHINFO_EXTENSION);
            $data['size'] = filesize($media);
            $route = str_replace($cur_dir, $dir_route, $media);
            $route = str_replace('index' . CONTENT_EXT, '', $route);
            $route = str_replace(CONTENT_EXT, '', $route);
            $route = '/' . trim($route, '/');
            $data['route'] = $route;
            $data['url'] = $base_url . $route;
            $t->toc('default');

            // get media metadata
            $meta = array_key_exists('meta', $data) && is_array($data['meta']) ? $data['meta'] : array();

            // Extend the data provided with each page by hooking into the data array
            $env->run_hooks('after_indexing_content', array(&$data, $meta));
            $t->toc('end ' . $media);

            if ($config['order_by'] == 'date' && isset($meta['date'])) {
                $sorted_medias[$meta['date'] . $date_id] = $data;
                $date_id++;
            } else {
                $sorted_medias[] = $data;
            }
        }

        // order direction
        if ($config['order'] == 'desc')
            krsort($sorted_medias);
        else
            ksort($sorted_medias);

        $t->toc('sort');

        // type ordering
        if ($config['order_by'] == 'type'){
            if(!array_key_exists('index_type_order', $config)){
                $type_order = array('directory', 'markdown', 'image', 'unknown', '');
            } else {
                $type_order = $config['index_type_order'];
            }
            $sorted_medias = self::sort_by_type($sorted_medias, $type_order);
            $t->toc('sort_by_type');
        }

        // debug
        if($config['debug']){
            echo "<!-- Index:\n";
            var_dump($sorted_medias);
            echo "-->\n";
            $t->toc('debug');
        }
    }

    /**
     * Sort a media array by file type
     *
     * @param array $medias the media array
     * @param array $type_order the order of main types
     * @return array the sorted media array
     */
    public static function sort_by_type($medias, $type_order){
        // create type groups
        $types = array();
        foreach($medias as $media){
            $type = array_key_exists('type', $media) ? $media['type'] : '';
            if(!array_key_exists($type, $types))
                $types[$type] = array();
            $types[$type][] = $media;
        }
        // flatten
        $sorted_medias = array();
        foreach($type_order as $type){
            if(empty($types[$type]))
                continue;
            $sorted_medias = array_merge($sorted_medias, $types[$type]);
            unset($types[$type]);
        }
        foreach($types as $list){
            $sorted_medias = array_merge($sorted_medias, $list);
        }
        return $sorted_medias;
    }

    /**
     * Create data for default files that have no indexing plugin
     *
     * @param string $file the file to create data for
     * @return array data array
     */
    public static function get_default_content($file){
        global $config;
        $time = filectime($file);
        return array(
            'type'      => 'unknown',
            'date'      => date('Y/m/d', $time),
            'date_formatted'    => date($config['date_format'], $time),
            'meta'      => array()
        );
    }
}

<?php

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
    public function get_index($file, $env, $headers)
    {
	    global $config;

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

		$medias = Files::find($cur_dir, '', FALSE); // every file, single level
		$sorted_medias = array();
		$date_id = 0;
        foreach ($medias as $key => $media) {
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
                    continue;
                }
            }

            // load index data
            $data = array();
            $env->run_hooks('indexing_content', array($media, $headers, &$data));
            if(empty($data))
                $data = $this->get_default_content($media);

            // force parameters
            $data['file'] = $media;
            $data['filename'] = pathinfo($media, PATHINFO_BASENAME);
            $data['extension'] = pathinfo($media, PATHINFO_EXTENSION);
            $data['size'] = filesize($media);
		    $route = str_replace($cur_dir, $dir_route . '/', $media);
		    $route = str_replace('index' . CONTENT_EXT, '', $route);
            $route = str_replace(CONTENT_EXT, '', $route);
            $route = '/' . trim($route, '/');
            $data['route'] = $route;
            $data['url'] = $base_url . $route;

            // get media metadata
            $meta = $data['meta'];
            if(!is_array($meta)){
                $meta = array();
            }

			// Extend the data provided with each page by hooking into the data array
			$env->run_hooks('after_indexing_content', array(&$data, $meta));

			if ($order_by == 'date' && isset($meta['date'])) {
				$sorted_medias[$meta['date'] . $date_id] = $data;
				$date_id++;
			}
			else
				$sorted_medias[] = $data;
		}

		if ($order == 'desc')
			krsort($sorted_medias);
		else
			ksort($sorted_medias);
        echo "<!-- Index:\n";
        var_dump($sorted_medias);
        echo "-->\n";
		return $sorted_medias;
    }

    /**
     * Create data for default files that have no indexing plugin
     *
     * @param string $file the file to create data for
     * @return array data array
     */
    public function get_default_content($file){
        global $config;
        $time = filectime($file);
        return array(
			'date'      => date('yyyyMMdd', $time),
            'date_formatted'    => date($config['date_format'], $time),
            'meta'      => array()
		);
    }
}

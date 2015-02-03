<?php

include_once ROOT_DIR . '/chrono.php';

class Media_Index_Image {

    private $type;

    public function __construct() {
        if(function_exists('finfo_open')){
            $this->type = 0;
        } else if(function_exists('mime_content_type')){
            $this->type = 1;
        } else {
            $this->type = 2;
        }
    }
    
    public function indexing_content($file, $headers, &$data){
        // only treat images
        $t = tictoc('plugin::media_index_image:');
        if(!$this->is_image($file)){
            $t->toc('is_image:false');
            return;
        }
        $t->toc('is_image:true');

        // image meta
        $meta = getimagesize($file);

        // configuration for versions
        global $config;

        // time of creation
        $time = filectime($file);

        // image data
        $data = array(
            'title'     => basename($file),
            'type'      => 'image',
            'is_image'  => TRUE,
            'width'     => $meta[0],
            'height'    => $meta[1],
            'is_wide'   => $meta[0] > $meta[1],
            'is_tall'   => $meta[0] < $meta[1],
            'is_square' => $meta[0] == $meta[1],
            'date'      => date('Ymd', $time),
            'date_formatted'    => date($config['date_format'], $time),
            'meta'      => $meta
        );
    }

    public function is_image($file){
        switch($this->type){
        case 0:
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            return Text::starts_with($mime, 'image');
            case 1:
                return Text::starts_with(mime_content_type($file), 'image');
            default:
                return !!@getimagesize($file);
        }
    }

}

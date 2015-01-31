<?php

class Media_Index_Image {
    
    public function indexing_content($file, $headers, &$data){
        // only treat images
        $meta = @getimagesize($file);
        if(!$meta)
            return;

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
            'date'      => date('Ymd', $time),
            'date_formatted'    => date($config['date_format'], $time),
            'meta'      => $meta
        );
    }

}

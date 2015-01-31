<?php

class Media_Index_Directory {
    
    public function indexing_content($file, $headers, &$data){
        // only treat directories
        if(!is_dir($file))
            return;

        // configuration for ignore/accept
        global $config;

        // count children files
        $count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));

        // time of creation
        $time = filectime($file);

        // count files
        // image data
        $data = array(
            'title'     => basename($file),
            'type'      => 'directory',
            'is_dir'    => TRUE,
            'children'  => $count,
            'date'      => date('Ymd', $time),
            'date_formatted'    => date($config['date_format'], $time),
        );
    }

}

<?php

include_once ROOT_DIR . '/markdown.php';

class Media_Index_Markdown {
    
    public function indexing_content($file, $headers, &$data){
        // only treat markdown files
        if(!Text::ends_with($file, CONTENT_EXT))
            return;

        // configuration for the date format
        global $config;

        // Get title and format $page
        $page_content = file_get_contents($file);
        $page_meta = Markdown::read_file_meta($page_content, $headers);
        $page_content = Markdown::parse_content($page_content);
        $data = array(
            'title'     => isset($page_meta['title']) ? $page_meta['title'] : '',
            'type'      => 'markdown',
            'author'    => isset($page_meta['author']) ? $page_meta['author'] : '',
            'date'      => isset($page_meta['date']) ? $page_meta['date'] : '',
            'date_formatted'    => isset($page_meta['date']) ? date($config['date_format'], strtotime($page_meta['date'])) : '',
            'content'   => $page_content,
            'excerpt'   => Text::limit_words(strip_tags($page_content), $config['excerpt_length']),
            'meta'      => $page_meta
        );
    }

}

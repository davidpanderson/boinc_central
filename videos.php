<?php

require_once('../inc/util.inc');

// videos are 1616 x 735

function youtube_video($key, $caption, $width=538, $height=255) {
    echo sprintf(
        '<p>%s<p>
        <iframe width="%d" height="%d" src="https://www.youtube.com/embed/%s" frameborder="0"
        allowfullscreen
        ></iframe>',
        $caption, $width, $height, $key
    );
}
page_head('Video tutorials');

youtube_video('NJ8sxnipMUs', 'Autodock');
youtube_video('ypBh-Sw_jTg', 'Vina');
youtube_video('dGiZbEje-Ok', 'Vinardo');
youtube_video('g7jago5-TvU', 'Vina and Raccoon2');
page_tail();

?>

<?php
// Your code to enqueue parent theme styles
function enqueue_parent_styles() {
   wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );


// Hide prev & next post from sigle post

add_filter( 'astra_single_post_navigation_enabled', '__return_false' );

?>
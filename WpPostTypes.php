<?php

class WpPostTypes
{
 //public const home;


    /**
     * @param $wp_query
     * @return mixed
     */
    public static function getSinglePost_format($wp_query): mixed
    {
        //Post & Custom Post
        $post_format = get_post_type($wp_query->post);
        if ($post_format === false && isset($wp_query->query_vars['post_type'])) {
            $post_format = $wp_query->query_vars['post_type'];
        }
        if ($post_format === 'post') {
            $post_format_wp = get_post_format($wp_query->post);
            $post_format = ($post_format_wp === 'standard' || !$post_format_wp) ? 'post' : "post-$post_format_wp";
        }
        return $post_format;
    }

    /** Check if the current request is the home page or a single page and so on
     * @param $wp_query
     * @return string
     */
    public static function CalculatePostFormat($wp_query): string
    {
        $post_format = null;
        if (is_home() || is_front_page()) {
            $post_format = 'home';
        } elseif (is_archive()) {
            $post_format = 'archive';
        } elseif (is_search()) {
            $post_format = 'search';
        } elseif (is_attachment()) {
            $post_format = 'attachment';
        } elseif (is_page()) {
            $post_format = 'page';
        } elseif (is_single()) {
            $post_format = WpPostTypes::getSinglePost_format($wp_query);
        }
        return $post_format;
    }
}
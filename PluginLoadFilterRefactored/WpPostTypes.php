<?php

class WpPostTypes
{
 //public const home;


    /**
    * Make taxonomies and posts available to 'plugin load filter'.
    * force register_taxonomy (category, post_tag, post_format)
     */
    public static function getSinglePost_format($wp_query)
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
     */
    public static function CalculatePostFormat(): string
    {
        global $wp_query;
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

    public static function force_initial_taxonomies(): void
    {
        global $wp_actions;
        $wp_actions['init'] = 1;
        create_initial_taxonomies();
        create_initial_post_types();
        unset($wp_actions['init']);
    }
}
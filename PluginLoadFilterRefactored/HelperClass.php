<?php

class HelperClass
{
    /**
     * Retrieve the current user object.
     * @return int|null Current user WP_User object
     */
    public static function wp_get_current_user(): ?int
    {
        if (!function_exists('wp_set_current_user')) {
            return 0;
        } else {
            return _wp_get_current_user();
        }
    }

    /**
     * Retrieve user info by user ID.
     * @param int $user_id User ID
     * @return WP_User|bool WP_User object on success, false on failure.
     */
    public static function get_userdata(int $user_id)
    {
        return self::get_user_by('id', $user_id);
    }

    /**
     * Retrieve user info by a given field
     * @param string $field The field to retrieve the user with. id | slug | email | login
     * @param string $value A value for $field. A user ID, slug, email address, or login name.
     * @return WP_User|bool WP_User object on success, false on failure.
     */
    public static function get_user_by(string $field, string $value): mixed //WP_User|bool
    {
        $userdata = WP_User::get_data_by($field, $value);

        if (!$userdata)
            return false;

        $user = new WP_User;
        $user->init($userdata);

        return $user;
    }

    /**
         * Checks if the current visitor is a logged in user.
         * @return bool True if user is logged in, false if not logged in.
         */
    public static function is_user_logged_in(): bool
    {
        if (!function_exists('wp_set_current_user'))
            return false;

        $user = HelperClass::wp_get_current_user();

        if (is_a($user,WP_User)  && !$user->exists())
            return false;

        return true;
    }

    public static function IsMobile()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (empty($userAgent)) {
            $is_mobile = false;
        } elseif (strpos($userAgent, 'Mobile') !== false // many mobile devices (all iPhone, iPad, etc.)
            || strpos($userAgent, 'Android') !== false
            || strpos($userAgent, 'Silk/') !== false
            || strpos($userAgent, 'Kindle') !== false
            || strpos($userAgent, 'BlackBerry') !== false
            || strpos($userAgent, 'Opera Mini') !== false
            || strpos($userAgent, 'Opera Mobi') !== false) {
            $is_mobile = true;
        } else {
            $is_mobile = false;
        }
        $is_mobile = apply_filters('custom_is_mobile', $is_mobile);
        return $is_mobile;
    }

    public static function DoSomeWpQuery(): void
    {
        $GLOBALS['wp_the_query'] = new WP_Query();
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
        $GLOBALS['wp_rewrite'] = new WP_Rewrite();
        $GLOBALS['wp'] = new WP();
        //register_taxonomy(category, post_tag, post_format) support for is_archive
        WpPostTypes::force_initial_taxonomies();
        //Post Format, Custom Post Type support
//				add_action('parse_request', array(&$this, 'parse_request'));
        $GLOBALS['wp']->parse_request('');
        $GLOBALS['wp']->query_posts();
    }
}
<?php

class HelperClass
{
    /**
     * Retrieve the current user object.
     * @return WP_User Current user WP_User object
     */
    public static function wp_get_current_user(): int
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
    public static function get_userdata($user_id)
    {
        return self::get_user_by('id', $user_id);
    }

    /**
     * Retrieve user info by a given field
     * @param string $field The field to retrieve the user with. id | slug | email | login
     * @param string $value A value for $field. A user ID, slug, email address, or login name.
     * @return WP_User|bool WP_User object on success, false on failure.
     */
    public static function get_user_by(string $field, string $value): WP_User|bool
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

        if (!$user->exists())
            return false;

        return true;
    }
}
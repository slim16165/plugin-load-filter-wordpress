<?php
declare(strict_types=1)
    /*
      Plugin Name: plugin load filter [plf-filter]
      Description: Dynamically activated only plugins that you have selected in each page. [Note] plf-filter has been automatically installed / deleted by Activate / Deactivate of "load filter plugin".
      Version: 3.1.0
      Plugin URI: http://celtislab.net/wp_plugin_load_filter
      Author: enomoto@celtislab
      Author URI: http://celtislab.net/
      License: GPLv2
    */
defined('ABSPATH') || exit;

if (!is_admin())
{
    return;
}

//return;

/*error_reporting(E_ALL);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
ini_set('display_errors', 'On');
ini_set('error_reporting', E_ALL);

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);*/

/***************************************************************************
 * Plugin Load Filter( Admin, Desktop, Mobile, Page 4types filter)
 **************************************************************************/

$active_plugins = get_option('active_plugins', array());
$plugin_load_filter = new PluginLoadFilter();

class PluginLoadFilter
{
    private static $cache;

    public function __construct()
    {
        self::$cache = null;
        add_filter('pre_option_active_plugins', [&$this, 'active_plugins']);
    }

    public static function HandleMobileRelated($toReturn_PluginAttiviFinale, $pluginAttivi): array
    {
        if ($toReturn_PluginAttiviFinale == null)
        {
            #region

            $pluginAttiviFinale = array();

            //Equal treatment for when the wp_is_mobile is not yet available（wp-include/vars.php wp_is_mobile)
            $is_mobile = HelperClass::IsMobile();
            foreach ($pluginAttivi as $pluginAttivoCorrente)
            {
                $result = FilterType::CheckIfPluginIsToLoad($pluginAttivoCorrente, $is_mobile);

                if (!is_null($result))
                {
                    $pluginAttiviFinale[] = $result;
                }
            }

            #endregion
        }
        return $pluginAttiviFinale;
    }

    public static function HandleWordpressNotLoaded($toReturn)
    {
#region Anticipa (rispetto a WordPress)
        //Before plugins loaded, it does not use conditional branch such as is_home,
        // to set wp_query, wp in temporary query
        #endregion
        if ($toReturn == null && empty($GLOBALS['wp_the_query']))
        {
            if (FilterType::GesticiRewriteRule() === false)
                $toReturn = false;

            HelperClass::DoSomeWpQuery();
        }
        return $toReturn;
    }

    public static function HandleSinglePage($pluginAttivi): array
    {
        $REQUEST_URI = $_SERVER['REQUEST_URI'];
        $toReturn_PluginAttiviFinale = null;

        //Se presente in cache la lista dei plugin da mantenere attivi per questa pagina, esci (Se trova in cache quell'url e quella opzione lo restituisce)
        $cacheKey = md5("plf_url{$REQUEST_URI}");
        $toReturn_PluginAttiviFinale = self::$cache[$cacheKey]['active_plugins'];
        if (!empty($active_plugins))
        {
            return $toReturn_PluginAttiviFinale;
        }

        //Uso come key_UrlOrTipology per l'accesso alla cache l'url
        //$key_UrlOrTipology può valere 'heartbeat', 'admin-ajax' o un url immagino
        //Se trova un match e lo restituisce in output
        $urlkey = UrlRelated::CercaUnMatchConUrlPagina();

        if ($urlkey !== false && is_string($urlkey))
        {
            $toReturn_PluginAttiviFinale = UrlRelated::CheckIfPluginToUnload($pluginAttivi, $urlkey);
            self::$cache[$cacheKey]['active_plugins'] = $toReturn_PluginAttiviFinale;
        }

        return $toReturn_PluginAttiviFinale;
    }

    public function active_plugins()
    {
        //Se si sta installando wordpress non fare nulla
        if (defined('WP_SETUP_CONFIG') || defined('WP_INSTALLING'))
        {
            return false;
        }

        return $this->FilterActivePlugins();
    }

    //Plugin Load Filter Main (active plugins/modules filtering)
    //Only the plugins which are returned by this method will be loaded
    public static function FilterActivePlugins()
    {
        $shouldReturn = false;

        //Metodo principale
        $pluginAttivi = self::GetActivePluginList($shouldReturn); //if($shouldReturn) return $pluginAttivi_string;

        #region Cerca se ci sono impostazioni per URL - URL filter (max priority)

        //Admin mode exclude
        if($toReturn_PluginAttiviFinale == null && is_admin())
        {
            $toReturn_PluginAttiviFinale = false;
        }

        $toReturn_PluginAttiviFinale = self::HandleSinglePage($pluginAttivi);

        //Admin mode exclude
        if($toReturn_PluginAttiviFinale == null && is_admin())
        {
            $toReturn_PluginAttiviFinale = false;
        }

        $toReturn_PluginAttiviFinale = self::HandleWordpressNotLoaded($toReturn_PluginAttiviFinale);

        //Gestione articoli singoli
        if($toReturn_PluginAttiviFinale == null && is_single())
        {
            $shorcodes = self::GetShortcodesFromContent();
            $pluginDaRimuovereDiDefault = self::getPluginDaRimuovereDiDefault();
            $pluginAttivi = self::RimuoviPlugin($pluginDaRimuovereDiDefault, $pluginAttivi);
        }
        $pluginAttiviFinale = self::HandleMobileRelated($toReturn_PluginAttiviFinale, $pluginAttivi);

        if ($toReturn_PluginAttiviFinale != null)
            return $toReturn_PluginAttiviFinale;

        //TODO: c'è una doppia variabile $pluginAttiviFinale e $toReturn_PluginAttiviFinale
        return $pluginAttiviFinale;
    }

    private static function GetActivePluginList(bool &$shouldReturn)
    {
        global $wpdb;
        $shouldReturn = true;

        // prevent non-existent options from triggering multiple queries
        $notoptions = wp_cache_get('notoptions', 'options');

        if (isset($notoptions['active_plugins']))
        {
            return apply_filters('default_option_active_plugins', false);
        }

        $alloptions = wp_load_alloptions();

        if (isset($alloptions['active_plugins']))
        {
            $active_plugins = $alloptions['active_plugins'];
        } else
        {
            $active_plugins = wp_cache_get('active_plugins', 'options');

            if (false === $active_plugins)
            {
                $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = 'active_plugins' LIMIT 1"));

                // Has to be get_row instead of get_var because of funkiness with 0, false, null values
                if (is_object($row))
                {
                    $active_plugins = $row->option_value;
                    wp_cache_add('active_plugins', $active_plugins, 'options');
                } else
                {
                    // option does not exist, so we must cache its non-existence
                    if (!is_array($notoptions))
                    {
                        $notoptions = array();
                    }
                    $notoptions['active_plugins'] = true;
                    wp_cache_set('notoptions', $notoptions, 'options');
                }
            }
        }
        return maybe_unserialize($active_plugins);
    }

    private static function GetShortcodesFromContent(): array
    {
        global $wp_query;
        $post = $wp_query->posts[0];

//          print_r($post->post_content);

        $tagFound = array();
        //get shortcode regex shortcode_regex wordpress function - get_shortcode_regex();
        $shortcode_regex = '%\[([^[/]+)(\s[^[/]+)?\]%imU'; //il nome del tag va nel 1° capturing group

        if (preg_match_all($shortcode_regex, $post->post_content, $matches))
        {
            $tagFound = array_unique($matches[1]); //il nome del tag va nel 1° capturing group
        }

        return $tagFound;
    }

    private static function getPluginDaRimuovereDiDefault(): array
    {
        $pluginDaRimuovereDiDefault[] = "tablepress-responsive-tables/tablepress-responsive-tables.php";
        $pluginDaRimuovereDiDefault[] = "tablepress/tablepress.php";
        $pluginDaRimuovereDiDefault[] = "term-management-tools/term-management-tools.php";
        $pluginDaRimuovereDiDefault[] = "js_composer/js_composer.php";
        $pluginDaRimuovereDiDefault[] = "gik25-quotes/gik25-quotes.php";
        $pluginDaRimuovereDiDefault[] = "gik25-microdata/revious-microdata.php";
        $pluginDaRimuovereDiDefault[] = "classic-editor/classic-editor.php";
        $pluginDaRimuovereDiDefault[] = "kk-star-ratings/index.php";
        return $pluginDaRimuovereDiDefault;
    }

    private static function RimuoviPlugin(array $pluginDaRimuovereDiDefault, $pluginAttivi): mixed
    {
        foreach ($pluginDaRimuovereDiDefault as $pluginCorrente)
        {
            $i = array_search($pluginCorrente, $pluginAttivi, true);
            unset($pluginAttivi[$i]);
        }
        return $pluginAttivi;
    }

}

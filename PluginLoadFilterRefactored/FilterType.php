<?php

class FilterType
{
    public const Admin = '_admin';
    public const Pagefilter = '_pagefilter';
    public const Groups = 'group';
    public const Plfurlkey = 'plfurlkey';
    public const Urlkey = 'urlkey';
    public const Urlkeylist = 'urlkeylist';
    private static array $filter = array();  //Plugin Load Filter Setting option data

    public function __construct()
    {
        self::$filter = get_option('plf_option');
    }

    public static function getFilterUrlKeyList(): mixed
    {
        $plugins = self::$filter[FilterType::Urlkeylist];
        return FilterType::SplitAndTrim($plugins, PHP_EOL);

    }

    public static function GetUrlKey(): mixed
    {
        return self::$filter[FilterType::Urlkey];
    }

    public static function GetPagePlugins(): array
    {
        $plugins = self::$filter[FilterType::Pagefilter]['plugins'];
        return FilterType::SplitAndTrim($plugins);
    }

    public static function GetPluginsFilteredByPostFormat(string $post_format): array
    {
        if (empty($post_format))
            return [];

        $plugins = self::$filter[$post_format]['plugins'];
        return FilterType::SplitAndTrim($plugins);
    }

    public static function GetAdminPlugins(): array
    {
        $plugins = self::$filter[FilterType::Admin]['plugins'];
        return FilterType::SplitAndTrim($plugins, ",");
    }

    public static function GetPlfurlkeyPlugins($urlkey): mixed
    {
        return self::$filter['plfurlkey'][$urlkey]['plugins'];
    }

    public static function GetUrlsKeyList(array $keys_UrlOrTipology): array
    {
        $plugins = self::getFilterUrlKeyList();

        $ar_key = (!empty($filter_urls)) ? array_filter($plugins) : array();

        foreach ($ar_key as $key_UrlOrTipology)
        {
            $keys_UrlOrTipology[$key_UrlOrTipology] = $key_UrlOrTipology;
        }
        return $keys_UrlOrTipology;
    }



    public static function getKeys_UrlOrTipology(): array
    {
        $plugins1 = array();
        //Dai la priorità all'URL generico

        $plugins = self::GetUrlKey()['amp'];
        if (!empty($plugins))
        {
            $plugins1['amp'] = $plugins;
        }

        $plugins1 = self::GetUrlsKeyList($plugins1);

        $plugins1['wp-json'] = 'wp-json';
        $plugins1['heartbeat'] = 'admin-ajax';
        $plugins1['admin-ajax'] = 'admin-ajax';

        return $plugins1;
    }

    public static function GesticiRewriteRule(): bool
    {
        // If rewrite_rule is cleared when the plugin is disabled etc., custom post typeOfPage cannot be determined until rewrite_rule is updated
        // At this time, the custom post typeOfPage page will be skipped to the home, so it will not be possible to get out of the state where rewrite_rule cannot be updated after all
        // Monitor rewrite_rule to respond, skip plugin filtering if changed
        $rewrite_rules = get_option('rewrite_rules');
        $plf_queryvars = get_option('plf_queryvars');
        if (empty($rewrite_rules) || empty($plf_queryvars['rewrite_rules']) || $rewrite_rules !== $plf_queryvars['rewrite_rules'])
        {
            $plf_queryvars['rewrite_rules'] = (empty($rewrite_rules)) ? '' : $rewrite_rules;
            update_option('plf_queryvars', $plf_queryvars);
            return false;
        }
        return true;
    }

    public static function CheckIfPluginIsToLoad($pluginAttivoCorrente, string $is_mobile, $isPluginToUnload)
    {
        //page filter
        if (!$isPluginToUnload)
        {
            $plugins = self::GetPagePlugins();
            $isPluginToUnload = self::SeIlPluginVieneTrovatoèDaRimuovere2($plugins, $pluginAttivoCorrente);

            if ($isPluginToUnload)
            {
                $isPluginToUnload = MobileRelated::FiltraPerGruppi_extracted2($is_mobile, $pluginAttivoCorrente, true);
            }
        }

        if (!$isPluginToUnload)
        {
            return $pluginAttivoCorrente;
        }
    }

    private static function SplitAndTrim(string $stringa, string $separator = null): array
    {
        if ($separator === null)
            $separator = ',';

        $stringSplit = self::StringSplit($stringa, $separator);
        return array_map("trim", $stringSplit);
    }

    private static function StringSplit(string $stringa, string $separator = null): array
    {
        if ($separator === null)
            $separator = ',';

        return explode($separator, $stringa);
    }

    //TODO: usare ovunque si riesce
    public static function SeIlPluginVieneTrovatoèDaRimuovere(string $plugins, string $plugin): bool
    {
        $isPluginToUnload = !empty($plugins) && false !== strpos($plugins, $plugin);
        return $isPluginToUnload;
    }

    //TODO: usare ovunque si riesce
    public static function SeIlPluginVieneTrovatoèDaRimuovere2(array $plugins, array $plugin): bool
    {
        $isPluginToUnload = !empty($plugins) && in_array($plugin, $plugins, true);
        return $isPluginToUnload;
    }
}
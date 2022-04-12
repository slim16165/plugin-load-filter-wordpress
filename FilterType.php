<?php

class FilterType
{
    //	filter['_admin']
    //	filter['_pagefilter']
    //	filter['group']
    //	filter['plfurlkey']
    //	filter['urlkey']
    //	filter['urlkeylist']
    public const Admin = '_admin';
    public const Pagefilter = '_pagefilter';
    public const Groups = 'group';
    public const Urlkey = 'urlkey';
    public const Urlkeylist = 'urlkeylist';
    private array $filter = array();  //Plugin Load Filter Setting option data

    public function __construct()
    {
        self::$filter = get_option('plf_option');
    }

    public function getFilterUrlKeyList(): mixed
    {
        $plugins = $this->filter[FilterType::Urlkeylist];
        return $this->SplitAndTrim($plugins, PHP_EOL);

    }

    public function GetUrlKey(): mixed
    {
        return $this->filter[FilterType::Urlkey];
    }

    public function GetPagePlugins(): array
    {
        $plugins = $this->filter[FilterType::Pagefilter]['plugins'];
        return $this->SplitAndTrim($plugins);
    }

    public function GetPluginsFilteredByPostFormat(string $post_format): array
    {
        if(empty($post_format))
            return [];

        $plugins = $this->filter[$post_format]['plugins'];
        return $this->SplitAndTrim($plugins);
    }

    public function GetAdminPlugins(): array
    {
        $plugins = $this->filter[FilterType::Admin]['plugins'];
        return $this->SplitAndTrim($plugins, ",");
    }

    public function GetPlfurlkeyPlugins($urlkey): mixed
    {
        return $this->filter['plfurlkey'][$urlkey]['plugins'];
    }

    public function GetUrlsKeyList(array $keys_UrlOrTipology): array
    {
        $plugins = $this->filter->getFilterUrlKeyList();

        $ar_key = (!empty($filter_urls)) ? array_filter($plugins) : array();

        foreach ($ar_key as $key_UrlOrTipology) {
            $keys_UrlOrTipology[$key_UrlOrTipology] = $key_UrlOrTipology;
        }
        return $keys_UrlOrTipology;
    }

    private function SplitAndTrim(string $stringa, string $separator = null): array
    {
        if($separator == null)
            $separator = ',';

        $stringSplit = self::StringSplit($stringa, ',');
        return array_map("trim", $stringSplit);
    }

    private static function StringSplit(string $stringa, string $separator = null): array
    {
        if($separator == null)
            $separator = ',';

        return explode($separator, $stringa);
    }

    public function getKeys_UrlOrTipology(PluginLoadFilter $pluginLoadFilter): array
    {
        $plugins1 = array();
        //Dai la priorità all'URL generico

        $plugins = $this->filter->GetUrlKey()['amp'];
        if (!empty($plugins))
        {
            $plugins1['amp'] = $plugins;
        }

        $plugins1 = $this->filter->GetUrlsKeyList($plugins1);

        $plugins1['wp-json'] = 'wp-json';
        $plugins1['heartbeat'] = 'admin-ajax';
        $plugins1['admin-ajax'] = 'admin-ajax';

        return $plugins1;
    }

    public function GesticiRewriteRule(): bool
    {
        // If rewrite_rule is cleared when the plugin is disabled etc., custom post typeOfPage cannot be determined until rewrite_rule is updated
        // At this time, the custom post typeOfPage page will be skipped to the home, so it will not be possible to get out of the state where rewrite_rule cannot be updated after all
        // Monitor rewrite_rule to respond, skip plugin filtering if changed
        $rewrite_rules = get_option('rewrite_rules');
        $plf_queryvars = get_option('plf_queryvars');
        if (empty($rewrite_rules) || empty($plf_queryvars['rewrite_rules']) || $rewrite_rules !== $plf_queryvars['rewrite_rules']) {
            $plf_queryvars['rewrite_rules'] = (empty($rewrite_rules)) ? '' : $rewrite_rules;
            update_option('plf_queryvars', $plf_queryvars);
            return false;
        }
        return true;
    }

    public function CheckIfPluginIsToLoad($pluginAttivoCorrente, string $is_mobile)
    {
        $unload = false;

        //admin mode filter
        $plugins = $this->filter2->GetAdminPlugins();
        if (!empty($plugins) && in_array($pluginAttivoCorrente, $plugins, true)) {
            $unload = true;
        }

        //page filter
        if (!$unload) {
            $plugins = $this->filter2->GetPagePlugins();

            if (!empty($plugins) && in_array($pluginAttivoCorrente, $plugins, true)) {
                $unload = true;
                $unload = $this->extracted2($is_mobile, $pluginAttivoCorrente, $unload);
            }
        }
        if (!$unload) //Sopra c'è lo stesso if...
        {
            return $pluginAttivoCorrente;
        }
    }

    private function extracted2(string $is_mobile, $pluginAttivoCorrente, &$unload): array
    {
        //desktop/mobile device disable filter
        $disabilitaPerQuestoDevice = true;

        global $wp_query;
        $mobileOrDesktop = ($is_mobile) ? 'mobile' : 'desktop';
        if (is_singular() && is_object($wp_query->post))
        {
            $pageBehaviourMobileOrDesktop = PluginLoadFilter_extra::extracted($mobileOrDesktop, $pluginAttivoCorrente, $disabilitaPerQuestoDevice);
        }

        $unload = $this->FiltraPerGruppi($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $disabilitaPerQuestoDevice);
        return $unload;
    }

    private function FiltraPerGruppi($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, mixed $pluginAttivoCorrente): bool
    {
        $disabilitaPerQuestoDevice = $this->extracted1($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente);

        if (!$disabilitaPerQuestoDevice)
        {
            if (!is_embed())
            {
                $unload = $this->HandleSingleMobileOrDesktop($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $pageFormatOptions);

                if ($pageFormatOptions === false) {
                    $this->isUnload($pluginAttivoCorrente, $unload);
                }
            }
        }
        return $unload;
    }

    private function HandleSingleMobileOrDesktop($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, $pluginAttivoCorrente, &$pageFormatOptions): bool
    {
        $unload = false;
        $pageFormatOptions = false;

        if (is_singular())
        {
            if (!empty($pageBehaviourMobileOrDesktop) && $pageBehaviourMobileOrDesktop['filter'] === 'include') {
                $pageFormatOptions = true;
                $MobileOrDesktop = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
                if (false !== strpos($MobileOrDesktop, $pluginAttivoCorrente)) {
                    $unload = false;
                }
            }
        }
        return $unload;
    }

    private function isUnload($pluginAttivoCorrente, bool &$unload): void
    {
        $post_format = WpPostTypes::CalculatePostFormat();

        $plugins = $this->filter2->GetPluginsFilteredByPostFormat($post_format);

        if (!empty($plugins)) {
            if (in_array($pluginAttivoCorrente, $plugins, true)) {
                $unload = false;
            }
        }
    }

    private function extracted1($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, $pluginAttivoCorrente): bool
    {
        $filtriPerGruppi = $this->filter2->GetFilterGroups()['group'][$mobileOrDesktop];

        if (empty($pageBehaviourMobileOrDesktop) || $pageBehaviourMobileOrDesktop['filter'] === 'default')
        {
            $plugins = $filtriPerGruppi['plugins'];

            if (!empty($plugins)
                && false !== strpos($plugins, $pluginAttivoCorrente)) {
                $disabilitaPerQuestoDevice = false;
            }
        }
        return $disabilitaPerQuestoDevice;
    }
}
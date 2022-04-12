<?php

class MobileRelated
{
    public static function extracted(string $mobileOrDesktop, mixed $pluginAttivoCorrente, bool &$disabilitaPerQuestoDevice): array
    {
        global $wp_query;
        $specificPagePostMeta_option = get_post_meta($wp_query->post->ID, '_plugin_load_filter', true);

        $behaviourMobileOrDesktop = self::getPageBehaviourMobileOrDesktop($specificPagePostMeta_option);

        if ($behaviourMobileOrDesktop['filter'] === 'include') {
            $pageBehaviour = $behaviourMobileOrDesktop[$mobileOrDesktop];
            //strpos ( string $haystack , mixed $needle [, int $offset = 0 ] ) : int
            if (false !== strpos($pageBehaviour, $pluginAttivoCorrente)) {
                $disabilitaPerQuestoDevice = false;
            }
        }
        return $behaviourMobileOrDesktop;
    }

    public static function getPageBehaviourMobileOrDesktop($specificPagePostMeta_option)
    {
        $defaultBehaviour = array('filter' => 'default',
            'desktop' => '',
            'mobile' => '');

        $pageBehaviourMobileOrDesktop = (!empty($specificPagePostMeta_option)) ? $specificPagePostMeta_option : $defaultBehaviour;
        $pageBehaviourMobileOrDesktop = wp_parse_args($pageBehaviourMobileOrDesktop, $defaultBehaviour);
        return $pageBehaviourMobileOrDesktop;
    }

    public static function FiltraPerGruppi_extracted1($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, $pluginAttivoCorrente): bool
    {
        //TODO: non esiste il metodo GetFilterGroups
        $filtriPerGruppi = self::GetFilterGroups()['group'][$mobileOrDesktop];

        if (empty($pageBehaviourMobileOrDesktop) || $pageBehaviourMobileOrDesktop['filter'] === 'default')
        {
            $plugins = $filtriPerGruppi['plugins'];
            $disabilitaPerQuestoDevice = FilterType::SeIlPluginVieneTrovatoèDaRimuovere($plugins, $pluginAttivoCorrente);
        }
        return $disabilitaPerQuestoDevice;
    }

    public static function FiltraPerGruppi_extracted($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, $pluginAttivoCorrente, $pageFormatOptions): array
    {
        if (!is_embed())
        {
            $isPluginToUnload = self::HandleSingleMobileOrDesktop($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $pageFormatOptions);

            if ($pageFormatOptions === false)
            {
                $post_format = WpPostTypes::CalculatePostFormat();

                $plugins = FilterType::GetPluginsFilteredByPostFormat($post_format);
                $isPluginToUnload = SeIlPluginVieneTrovatoèDaRimuovere2($plugins, $pluginAttivoCorrente);
            }
        }

        return $isPluginToUnload;
    }

    public static function HandleSingleMobileOrDesktop($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, $pluginAttivoCorrente, &$pageFormatOptions): bool
    {
        $isPluginToUnload = false;
        $pageFormatOptions = false;

        if (is_singular())
        {
            if (!empty($pageBehaviourMobileOrDesktop) && $pageBehaviourMobileOrDesktop['filter'] === 'include')
            {
                $pageFormatOptions = true;
                $MobileOrDesktop = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
                if (false !== strpos($MobileOrDesktop, $pluginAttivoCorrente))
                {
                    $isPluginToUnload = false;
                }
            }
        }
        return $isPluginToUnload;
    }

    public static function FiltraPerGruppi_extracted2(string $is_mobile, $pluginAttivoCorrente): array
    {
        //desktop/mobile device disable filter
        $disabilitaPerQuestoDevice = true;
        $isPluginToUnload = true;

        global $wp_query;
        $mobileOrDesktop = ($is_mobile) ? 'mobile' : 'desktop';
        if (is_singular() && is_object($wp_query->post))
        {
            $pageBehaviourMobileOrDesktop = MobileRelated::extracted($mobileOrDesktop, $pluginAttivoCorrente, $disabilitaPerQuestoDevice);
        }

        //FiltraPerGruppi
        $disabilitaPerQuestoDevice = MobileRelated::FiltraPerGruppi_extracted1($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente);

        if (!$disabilitaPerQuestoDevice)
            $isPluginToUnload = MobileRelated::FiltraPerGruppi_extracted($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $pageFormatOptions);

        return $isPluginToUnload;
    }

    public static function HandleMobileRelated($toReturn_PluginAttiviFinale, $pluginAttivi): array
    {
        if ($toReturn_PluginAttiviFinale != null)
            return $toReturn_PluginAttiviFinale;

        $pluginAttiviFinale = array();

        //Equal treatment for when the wp_is_mobile is not yet available（wp-include/vars.php wp_is_mobile)
        $is_mobile = HelperClass::IsMobile();
        foreach ($pluginAttivi as $pluginAttivoCorrente)
        {
            //admin mode filter
            $plugins = self::GetAdminPlugins();
            $isPluginToUnload = self::SeIlPluginVieneTrovatoèDaRimuovere2($plugins, $pluginAttivoCorrente);

            $result = FilterType::CheckIfPluginIsToLoad($pluginAttivoCorrente, $is_mobile, $isPluginToUnload);

            if (!is_null($result))
            {
                $pluginAttiviFinale[] = $result;
            }
        }

        return $pluginAttiviFinale;
    }
}
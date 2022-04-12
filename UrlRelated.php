<?php

class UrlRelated
{

    public static function CercaUrl(mixed $kwd): mixed
    {
        return preg_match("#([/&.?=])$kwd([/&.?=]|$)#u", $_SERVER['REQUEST_URI']);
    }

    public static function getUrlkey(array $keys_UrlOrTipology)
    {
        $urlkey = null;

        foreach ($keys_UrlOrTipology as $key_UrlOrTipology => $kwd)
            if (self::CercaUrl($kwd))
            {
                if (self::ShouldSkipAnyAction($key_UrlOrTipology))
                    continue;
                else
                    $urlkey = $key_UrlOrTipology;

                return $urlkey;
            }
        return $urlkey;
    }

    public static function ShouldSkipAnyAction($currentUrl): bool
    {
        //Se è heartbeat o admin-ajax skippa
        $skip_actions = false;
        $action = $_REQUEST['action'];

        if ($currentUrl === 'heartbeat')
        {
            if (empty($action) || $action !== 'heartbeat')
            {
                $skip_actions = true;
            }
        } else if ($currentUrl === 'admin-ajax')
        {
            //exclude action : plugin_load_filter
            if (!(empty($action) || $action != 'revious-microdata'))
            {
                $skip_actions = true;
            }
        }

        return $skip_actions;
    }

    public static function CercaUnMatchConUrlPagina(): mixed
    {
        $plugins = FilterType::getKeys_UrlOrTipology();

        $urlkey = UrlRelated::getUrlkey($plugins);
        return $urlkey;
    }

    public static function CheckIfPluginToUnload($pluginAttivi, string $urlkey): array
    {
        $pluginAttiviFinale = array();

        foreach ($pluginAttivi as $plugin)
        {
            $plugins = FilterType::GetPlfurlkeyPlugins($urlkey);

            //Se il plugin viene trovato viene rimosso
            $isPluginToUnload = FilterType::SeIlPluginVieneTrovatoèDaRimuovere($plugins, $plugin);

            if (!$isPluginToUnload)
            {
                $pluginAttiviFinale[] = $plugin;
            }
        }

        return $pluginAttiviFinale;
    }
}
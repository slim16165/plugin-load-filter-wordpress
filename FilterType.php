<?php

class FilterType
{
    public const Admin = '_admin';
    public const Pagefilter = '_pagefilter';
    public const Groups = 'group';
    public const Urlkey = 'urlkey';
    public const Urlkeylist = 'urlkeylist';
    private array $filter = array();  //Plugin Load Filter Setting option data

    public function getFilterUrlKeyList(): mixed
    {
        $plugins = $this->filter[self::Urlkeylist];
        return $this->SplitAndTrim($plugins, PHP_EOL);

    }

    public function GetUrlKey(): mixed
    {
        return $this->filter[self::Urlkey];
    }

    public function GetPagePlugins(): array
    {
        $plugins = $this->filter[self::Pagefilter]['plugins'];
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
        $plugins = $this->filter[self::Admin]['plugins'];
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
}
<?php

class PluginLoadFilter_extra
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
}
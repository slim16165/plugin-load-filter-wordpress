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

    if(!is_admin()) {
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
        return $this->filter[self::Urlkeylist];
    }

    public function GetFilterUrlKey(): mixed
    {
        return $this->filter[self::Urlkey];
    }

    public function GetPageFilterPlugins(): mixed
    {
        return $this->filter[self::Pagefilter]['plugins'];
    }

    public function GetFilterPlfurlkeyPlugins($urlkey): mixed
    {
        return $this->filter['plfurlkey'][$urlkey]['plugins'];
    }
    public function getFilterAdminPlugins(): mixed
    {
        return $this->filter[self::Admin]['plugins'];
    }

    public function GetUrlsKeyList(array $keys_UrlOrTipology): array
    {
        $filter_urls = explode(PHP_EOL, $this->filter->getFilterUrlKeyList());
        $filter_urls2 = array_map("trim", $filter_urls);

        $ar_key = (!empty($filter_urls)) ? array_filter($filter_urls2) : array();

        foreach ($ar_key as $key_UrlOrTipology) {
            $keys_UrlOrTipology[$key_UrlOrTipology] = $key_UrlOrTipology;
        }
        return $keys_UrlOrTipology;
    }
}

	class PluginLoadFilter
	{

		//	filter['_admin']
		//	filter['_pagefilter']
		//	filter['group']
		//	filter['plfurlkey']
		//	filter['urlkey']
		//	filter['urlkeylist']
		private FilterType $filter2;
		private $cache;

		public function __construct()
		{
//			$this->filter = get_option('plf_option');
			$this->cache = null;
			add_filter('pre_option_active_plugins', array(&$this, 'active_plugins'));
		}

        /**
         * @param string $stringa
         * @return string[]
         */
        private static function StringSplit(string $stringa): array
        {
            return explode(',', $stringa);
        }

        /**
         * @param $specificPagePostMeta_option
         * @param string $mobileOrDesktop
         * @param mixed $pluginAttivoCorrente
         * @param bool $disabilitaPerQuestoDevice
         * @return array
         */
        private static function extracted($specificPagePostMeta_option, string $mobileOrDesktop, mixed $pluginAttivoCorrente, bool $disabilitaPerQuestoDevice): array
        {
            $defaultBehaviour = array('filter' => 'default',
                'desktop' => '',
                'mobile' => '');

            $pageBehaviourMobileOrDesktop = (!empty($specificPagePostMeta_option)) ? $specificPagePostMeta_option : $defaultBehaviour;
            $pageBehaviourMobileOrDesktop = wp_parse_args($pageBehaviourMobileOrDesktop, $defaultBehaviour);
            if ($pageBehaviourMobileOrDesktop['filter'] === 'include') {
                $pageBehaviour = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
                //strpos ( string $haystack , mixed $needle [, int $offset = 0 ] ) : int
                if (false !== strpos($pageBehaviour, $pluginAttivoCorrente)) {
                    $disabilitaPerQuestoDevice = false;
                }
            }
            return array($pageBehaviourMobileOrDesktop, $disabilitaPerQuestoDevice);
        }


        private function ShouldSkipAnyAction($currentUrl): bool
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
			}
            else if ($currentUrl === 'admin-ajax')
			{
				//exclude action : plugin_load_filter
				if (!(empty($action) || $action != 'revious-microdata'))
				{
					$skip_actions = true;
				}
			}

			return $skip_actions;
		}

        public function active_plugins()
		{
            //Se si sta installando wordpress non fare nulla
            if (defined('WP_SETUP_CONFIG') || defined('WP_INSTALLING')) {
                return false;
            }

			return $this->FilterActivePlugins();
		}



        //Plugin Load Filter Main (active plugins/modules filtering)
		//Only the plugins which are returned by this method will be loaded
		public function FilterActivePlugins()
		{
			$shouldReturn = false;
            $REQUEST_URI = $_SERVER['REQUEST_URI'];


            //Metodo principale
			$pluginAttivi_string = $this->GetActivePluginList($shouldReturn); //if($shouldReturn) return $pluginAttivi_string;
            $pluginAttivi = maybe_unserialize($pluginAttivi_string);

			#region Cerca se ci sono impostazioni per URL - URL filter (max priority)

            //Se presente in cache la lista dei plugin da mantenere attivi per questa pagina, esci (Se trova in cache quell'url e quella opzione lo restituisce)
            $cacheKey = md5("plf_url{$REQUEST_URI}");
            $active_plugins = $this->cache[$cacheKey]['active_plugins'];
            if (!empty($active_plugins))
            {
                return $active_plugins;
            }

            //Uso come key_UrlOrTipology per l'accesso alla cache l'url
            //$key_UrlOrTipology può valere 'heartbeat', 'admin-ajax' o un url immagino
            //Se trova un match e lo restituisce in output
            $urlkey = $this->CercaUnMatchConUrlPagina();

            if ($urlkey !== false && is_string($urlkey))
            {
                return $this->extracted1($pluginAttivi, $urlkey, $cacheKey);
            }


			#endregion

			#region Boh.. però non mi serve

			//Admin mode exclude
			if (!$urlkey && is_admin()) {
                return false;
            }

			#endregion

			#region Anticipa (rispetto a Wordpress)
            //Before plugins loaded, it does not use conditional branch such as is_home,
            // to set wp_query, wp in temporary query
			if (empty($GLOBALS['wp_the_query']))
			{
				if ($this->GesticiRewriteRule() === false) {
                    return false;
                }

                $this->DoSomeWpQuery();
            }

			#endregion


            $shorcodes = $this->GetShortcodesFromContent();

            //Gestione articoli singoli

            if(is_single())
            {
                $pluginDaRimuovereDiDefault = $this->getPluginDaRimuovereDiDefault();
                $pluginAttivi = $this->RimuoviPlugin($pluginDaRimuovereDiDefault, $pluginAttivi);
            }

			#region

			$pluginAttiviFinale = array();


            //Equal treatment for when the wp_is_mobile is not yet available（wp-include/vars.php wp_is_mobile)
            $is_mobile = HelperClass::IsMobile();
            foreach ($pluginAttivi as $pluginAttivoCorrente)
			{
				$result = $this->CheckIfPluginIsToLoad($pluginAttivoCorrente, $is_mobile);

				if (!is_null($result)) {
                    $pluginAttiviFinale[] = $result;
                }
			}

			#endregion

			return $pluginAttiviFinale;
		}

        //TODO: non ci si capisce un cazzo
		private function CercaUnMatchConUrlPagina(): int|string|null
        {
            $keys_UrlOrTipology = $this->getKeys_UrlOrTipology();

            $urlkey = null;

			foreach ($keys_UrlOrTipology as $key_UrlOrTipology => $kwd)
			{
				if ($this->CercaUrl($kwd))
				{
					if ($this->ShouldSkipAnyAction($key_UrlOrTipology))
                    {
                        continue;
                    }
					else
                    {
                        $urlkey = $key_UrlOrTipology;
                    }

					break;
				}
			}
			return $urlkey;
		}

		private function GesticiRewriteRule(): bool
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

		private function getArray_mapSplitAndTrim(string $stringa) : array
		{
            $stringSplit = self::StringSplit($stringa);
            return array_map("trim", $stringSplit);
		}


		private function CheckIfPluginIsToLoad($pluginAttivoCorrente, string $is_mobile)
		{
			global $wp_query;
            $mobileOrDesktop = ($is_mobile) ? 'mobile' : 'desktop';
			$unload = false;

			//admin mode filter
            $plugins1 = $this->filter2->GetAdminPlugins();
            if (!empty($plugins1))
			{
				$plugins = $this->getArray_mapSplitAndTrim($plugins1);

                if (in_array($pluginAttivoCorrente, $plugins, true)) {
                    $unload = true;
                }
			}

			//page filter
			if (!$unload)
			{
                $plugins = $this->filter2->GetPageFilterPlugins();

                if (!empty($plugins))
				{
                    $plugins2 = $this->getArray_mapSplitAndTrim($plugins);

                    if (in_array($pluginAttivoCorrente, $plugins2, true))
					{
						$unload = true;

						//desktop/mobile device disable filter
						$disabilitaPerQuestoDevice = true;


						if (is_singular() && is_object($wp_query->post))
						{
							$specificPagePostMeta_option = get_post_meta($wp_query->post->ID, '_plugin_load_filter', true);

                            [$pageBehaviourMobileOrDesktop, $disabilitaPerQuestoDevice] = self::extracted($specificPagePostMeta_option, $mobileOrDesktop, $pluginAttivoCorrente, $disabilitaPerQuestoDevice);
                        }

                        $unload = $this->FiltraPerGruppi($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $disabilitaPerQuestoDevice, $unload, $wp_query);
                    }
				}
			}
			if (!$unload) //Sopra c'è lo stesso if...
			{
				return $pluginAttivoCorrente;
			}
		}

		private function GetActivePluginList(bool &$shouldReturn)
		{
			global $wpdb;
			$shouldReturn = true;

			// prevent non-existent options from triggering multiple queries
			$notoptions = wp_cache_get('notoptions', 'options');

            if (isset($notoptions['active_plugins']))
			{
				$shouldReturn = true;
				return apply_filters('default_option_' . 'active_plugins', false);
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
			return $active_plugins;
		}

        private function GetShortcodesFromContent() : array
        {
            global $wp_query;
            $post = $wp_query->posts[0];

//          print_r($post->post_content);

            $tagFound = array();
            //get shortcode regex shortcode_regex wordpress function - get_shortcode_regex();
            $shortcode_regex = '%\[([^[/]+)(\s[^[/]+)?\]%imU'; //il nome del tag va nel 1° capturing group


            if (preg_match_all( $shortcode_regex, $post->post_content, $matches ) )
            {
                $tagFound = array_unique($matches[1]); //il nome del tag va nel 1° capturing group
            }

            return $tagFound;
        }

        /**
         * @param $pluginAttivi
         * @param string $urlkey
         * @param string $keyid
         * @return array
         */
        private function extracted1($pluginAttivi, string $urlkey, string $keyid): array
        {
            $pluginAttiviFinale = array();
            foreach ($pluginAttivi as $pluginAttivoCorrente) {
                $unload = false;
                $pluginDaRimuovere = $pluginAttivoCorrente;

                $plugins = $this->filter2->GetFilterPlfurlkeyPlugins($urlkey);

                if (!empty($plugins)) {
                    if (false !== strpos($plugins, $pluginDaRimuovere)) {
                        $unload = true;
                    }
                }
                if (!$unload) {
                    $pluginAttiviFinale[] = $pluginAttivoCorrente;
                }
            }
            $this->cache[$keyid]['active_plugins'] = $pluginAttiviFinale;
            return $pluginAttiviFinale;
        }

        private function DoSomeWpQuery(): void
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

        /**
         * @return array
         */
        private function getPluginDaRimuovereDiDefault(): array
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

        /**
         * @param array $pluginDaRimuovereDiDefault
         * @param $pluginAttivi
         * @return mixed
         */
        private function RimuoviPlugin(array $pluginDaRimuovereDiDefault, $pluginAttivi): mixed
        {
            foreach ($pluginDaRimuovereDiDefault as $pluginCorrente) {
                $i = array_search($pluginCorrente, $pluginAttivi, true);
                unset($pluginAttivi[$i]);
            }
            return $pluginAttivi;
        }

        private function extract2($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, mixed $pluginAttivoCorrente, bool $unload, $wp_query, mixed $filtriPerGruppi): bool
        {
            $pageFormatOptions = false;

            if (is_singular()) {
                if (!empty($pageBehaviourMobileOrDesktop) && $pageBehaviourMobileOrDesktop['filter'] === 'include') {
                    $pageFormatOptions = true;
                    $MobileOrDesktop = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
                    if (false !== strpos($MobileOrDesktop, $pluginAttivoCorrente)) {
                        $unload = false;
                    }
                }
            }
            if ($pageFormatOptions === false) {
                $post_format = WpPostTypes::CalculatePostFormat($wp_query);

                if (!empty($post_format) && is_string($post_format) && !empty($filtriPerGruppi[$post_format]['plugins'])) {
                    if (in_array($pluginAttivoCorrente, array_map("trim", explode(',', $filtriPerGruppi[$post_format]['plugins'])), true)) {
                        $unload = false;
                    }
                }
            }
            return $unload;
        }

        /**
         * @param $group
         * @param $pageBehaviourMobileOrDesktop
         * @param string $mobileOrDesktop
         * @param mixed $pluginAttivoCorrente
         * @param bool $disabilitaPerQuestoDevice
         * @param bool $unload
         * @param $wp_query
         * @return bool
         */
        private function FiltraPerGruppi($pageBehaviourMobileOrDesktop, string $mobileOrDesktop, mixed $pluginAttivoCorrente, bool $disabilitaPerQuestoDevice, bool $unload, $wp_query): bool
        {
            $filtriPerGruppi = $this->filter2->GetFilterGroups()['group'];
            if (empty($pageBehaviourMobileOrDesktop) || $pageBehaviourMobileOrDesktop['filter'] === 'default') {
                $var = $filtriPerGruppi[$mobileOrDesktop];
                if (!empty($var['plugins'])
                    && false !== strpos($var['plugins'], $pluginAttivoCorrente)) {
                    $disabilitaPerQuestoDevice = false;
                }
            }
            if ($disabilitaPerQuestoDevice) {
            } else {
                //oEmbed Content API
                if (is_embed()) {
                    $var1 = $filtriPerGruppi['content-card'];
                    if (!empty($var1['plugins'])) {
                        if (false !== strpos($var1['plugins'], $pluginAttivoCorrente)) {
                            $unload = false;
                        }
                    }
                } else {
                    $unload = $this->extract2($pageBehaviourMobileOrDesktop, $mobileOrDesktop, $pluginAttivoCorrente, $unload, $wp_query, $filtriPerGruppi);
                }
            }
            return $unload;
        }

        /**
         * @return array
         */
        private function getKeys_UrlOrTipology(): array
        {
            $keys_UrlOrTipology = array();
            //Dai la priorità all'URL generico

            foreach (['amp'] as $key) {
                $var = $this->filter2->GetFilterUrlKey()[$key];

                if (!empty($var)) {
                    $keys_UrlOrTipology[$key] = $var;
                }
            }

            $keys_UrlOrTipology = $this->filter2->GetUrlsKeyList($keys_UrlOrTipology);

            $keys_UrlOrTipology['wp-json'] = 'wp-json';
            $keys_UrlOrTipology['heartbeat'] = 'admin-ajax';
            $keys_UrlOrTipology['admin-ajax'] = 'admin-ajax';
            return $keys_UrlOrTipology;
        }

        /**
         * @param mixed $kwd
         * @return false|int
         */
        private function CercaUrl(mixed $kwd): int|false
        {
            return preg_match("#([/&.?=])$kwd([/&.?=]|$)#u", $_SERVER['REQUEST_URI']);
        }


    }

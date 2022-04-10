<?php
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

    if(!is_admin())
        return;

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
	 * pluggable.php defined function overwrite
	 * pluggable.php read before the query_posts () is processed by the current user undetermined
	 **************************************************************************/
	if (!function_exists('wp_get_current_user')) :
		/**
		 * Retrieve the current user object.
		 * @return WP_User Current user WP_User object
		 */
		function wp_get_current_user()
		{
			if (!function_exists('wp_set_current_user'))
			{
				return 0;
			} else
			{
				return _wp_get_current_user();
			}
		}
	endif;

	if (!function_exists('get_userdata')) :
		/**
		 * Retrieve user info by user ID.
		 * @param int $user_id User ID
		 * @return WP_User|bool WP_User object on success, false on failure.
		 */
		function get_userdata($user_id)
		{
			return get_user_by('id', $user_id);
		}
	endif;

	if (!function_exists('get_user_by')) :
		/**
		 * Retrieve user info by a given field
		 * @param string $field The field to retrieve the user with. id | slug | email | login
		 * @param int|string $value A value for $field. A user ID, slug, email address, or login name.
		 * @return WP_User|bool WP_User object on success, false on failure.
		 */
		function get_user_by($field, $value)
		{
			$userdata = WP_User::get_data_by($field, $value);

			if (!$userdata)
				return false;

			$user = new WP_User;
			$user->init($userdata);

			return $user;
		}
	endif;

	if (!function_exists('is_user_logged_in')) :
		/**
		 * Checks if the current visitor is a logged in user.
		 * @return bool True if user is logged in, false if not logged in.
		 */
		function is_user_logged_in()
		{
			if (!function_exists('wp_set_current_user'))
				return false;

			$user = wp_get_current_user();

			if (!$user->exists())
				return false;

			return true;
		}
	endif;

	/***************************************************************************
	 * Plugin Load Filter( Admin, Desktop, Mobile, Page 4types filter)
	 **************************************************************************/

	$active_plugins = get_option('active_plugins', array());
	$plugin_load_filter = new PluginLoadFilter();


	class PluginLoadFilter
	{

		//	$filter['_admin']
		//	$filter['_pagefilter']
		//	$filter['group']
		//	$filter['plfurlkey']
		//	$filter['urlkey']
		//	$filter['urlkeylist']
		private $filter = array();  //Plugin Load Filter Setting option data
		private $cache;

		function __construct()
		{
//			$this->filter = get_option('plf_option');
			$this->cache = null;
			add_filter('pre_option_active_plugins', array(&$this, 'active_plugins'));
		}

		//active plugins Filter
		private function CondizioniDiEsclusione($key_UrlOrTipology): bool
		{
			$skip_actions = false;

			if ($key_UrlOrTipology === 'heartbeat')
			{
				if (empty($_REQUEST['action']) || $_REQUEST['action'] !== 'heartbeat')
				{
					//continue = exclude any action
					$skip_actions = true; //continue;
				}
			} else if ($key_UrlOrTipology === 'admin-ajax')
			{
				//exclude action : plugin_load_filter
				if (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], array('revious-microdata')))
				{
					//continue = exclude any action
					$skip_actions = true; //continue;
				}
			}
//						else if ($key_UrlOrTipology === 'amp')
//						{
//							if (!defined('PLF_IS_AMP'))
//								define('PLF_IS_AMP', true);
//						}

			return $skip_actions;
		}

		protected static function IsMobile()
		{
			if (empty($_SERVER['HTTP_USER_AGENT']))
			{
				$is_mobile = false;
			} elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false // many mobile devices (all iPhone, iPad, etc.)
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'Silk/') !== false
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false
				|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false)
			{
				$is_mobile = true;
			} else
			{
				$is_mobile = false;
			}
			$is_mobile = apply_filters('custom_is_mobile', $is_mobile);
			return $is_mobile;
		}

		function active_plugins($default = false)
		{
			return $this->FilterActivePlugins();
		}

		//Make taxonomies and posts available to 'plugin load filter'.
		//force register_taxonomy (category, post_tag, post_format)
		function force_initial_taxonomies()
		{
			global $wp_actions;
			$wp_actions['init'] = 1;
			create_initial_taxonomies();
			create_initial_post_types();
			unset($wp_actions['init']);
		}

		//Plugin Load Filter Main (active plugins/modules filtering)
		//Only the plugins which are returned by this method will be loaded
		function FilterActivePlugins()
		{
			if (defined('WP_SETUP_CONFIG') || defined('WP_INSTALLING'))
				return false;

			$filter = $this->filter;

			$shouldReturn = false;

			$pluginAttivi_string = $this->CaricaPluginAttivi($shouldReturn);
//			if($shouldReturn)
//				return $pluginAttivi_string;

            $pluginAttivi = maybe_unserialize($pluginAttivi_string);

			#region Cerca se ci sono impostazioni per URL

            //URL filter (max priority)

			$urlkey = false; //in realtà poi diventa una stringa
			if (!empty($_SERVER['REQUEST_URI']))
			{
				#region Cache

				//Uso come key_UrlOrTipology per l'accesso alla cache l'url
				$keyid = md5("plf_url{$_SERVER['REQUEST_URI']}");

				//Se trova in cache quell'url e quella opzione lo restituisce
				if (!empty($this->cache[$keyid]['active_plugins']))
				{
					return $this->cache[$keyid]['active_plugins'];
				}

				#endregion

				#region Plugin to disable by Url and type

				//$kwd è l'url?
				//$key_UrlOrTipology può valere 'heartbeat', 'admin-ajax' o un url immagino
				//Se trova un matche lo restituisce in output
				$urlkey = $this->CercaUnMatchConUrlPagina();

				if ($urlkey != false && is_string($urlkey))
				{

					$pluginAttiviFinale = array();
					foreach ($pluginAttivi as $pluginAttivoCorrente)
					{
						$unload = false;
						$pluginDaRimuovere = $pluginAttivoCorrente;

                        if (!empty($filter['plfurlkey'][$urlkey]['plugins']))
						{
							if (false !== strpos($filter['plfurlkey'][$urlkey]['plugins'], $pluginDaRimuovere))
								$unload = true;
						}
						if (!$unload)
						{
							$pluginAttiviFinale[] = $pluginAttivoCorrente;
						}
					}
					$this->cache[$keyid]['active_plugins'] = $pluginAttiviFinale;
					return $pluginAttiviFinale;
				}
			}

			#endregion

			#endregion

			#region Boh.. però non mi serve

            echo $keyid;
			//Admin mode exclude
			if (is_admin() && $urlkey == false)
				return false;

			//Equal treatment for when the wp_is_mobile is not yet available（wp-include/vars.php wp_is_mobile)
			$is_mobile = self::IsMobile();

			//get_option is called many times, intermediate processing plf_queryvars to cache
			//La cache è separata da mobile e desktop
			$keyid = md5('plf_' . (string)$is_mobile . $_SERVER['REQUEST_URI']);
			if (!empty($this->cache[$keyid]['active_plugins']))
			{
				return $this->cache[$keyid]['active_plugins'];
			}

			#endregion

			#region Anticipa (rispetto a Wordpress) Before plugins loaded, it does not use conditional branch such as is_home, to set wp_query, wp in temporary query
			if (empty($GLOBALS['wp_the_query']))
			{
				if ($this->GesticiRewriteRule() === false)
					return false;

				$GLOBALS['wp_the_query'] = new WP_Query();
				$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
				$GLOBALS['wp_rewrite'] = new WP_Rewrite();
				$GLOBALS['wp'] = new WP();
				//register_taxonomy(category, post_tag, post_format) support for is_archive
				$this->force_initial_taxonomies();
				//Post Format, Custom Post Type support
//				add_action('parse_request', array(&$this, 'parse_request'));
				$GLOBALS['wp']->parse_request('');
				$GLOBALS['wp']->query_posts();
			}

			#endregion

			#region Capisce se la pagina è home o altro e se la risposta è sì, esce
			global $wp_query;
			if (!is_embed())
			{
				if ((is_home() || is_front_page() || is_archive() || is_search() || is_singular()) == false || (is_home() && !empty($_GET)))
				{
					//downloadmanager plugin downloadlink request [home]/?wpdmact=XXXXXX  exclude home GET query
					return false;

				} else if (is_singular() && empty($wp_query->post))
				{
					//documentroot special php file (wp-login.php, wp-cron.php, etc)  These are considered singular at this point, but are determined by the absence of post
					// Use URL filter if you want to filter
					// However, when bbPress private (private) page, post is not set yet at this point, but post_type is already set, so skip
					if (empty($wp_query->query_vars['post_type']))
					{
						return false;
					}
				}
			}

			$mobileOrDesktop = ($is_mobile) ? 'mobile' : 'desktop';

			#endregion

            if(is_admin())
                return;

            $shorcodes = $this->GetShortcodesFromContent();

            if(is_single())
            {
                $pluginDaRimuovereDiDefault[] = "tablepress-responsive-tables/tablepress-responsive-tables.php";
                $pluginDaRimuovereDiDefault[] = "tablepress/tablepress.php";
                $pluginDaRimuovereDiDefault[] = "term-management-tools/term-management-tools.php";
                $pluginDaRimuovereDiDefault[] = "js_composer/js_composer.php";
                $pluginDaRimuovereDiDefault[] = "gik25-quotes/gik25-quotes.php";
                $pluginDaRimuovereDiDefault[] = "gik25-microdata/revious-microdata.php";
                $pluginDaRimuovereDiDefault[] = "classic-editor/classic-editor.php";
                $pluginDaRimuovereDiDefault[] = "kk-star-ratings/index.php";

                foreach ($pluginDaRimuovereDiDefault as $pluginCorrente)
                {
                    $i = array_search($pluginCorrente, $pluginAttivi);
                    unset($pluginAttivi[$i]);
                }


//                print_r($pluginAttivi);
            }

			#region

			$pluginAttiviFinale = array();

			foreach ($pluginAttivi as $pluginAttivoCorrente)
			{
				$result = $this->CheckIfPluginIsToLoad($pluginAttivoCorrente, $mobileOrDesktop);

				if (!is_null($result))
					$pluginAttiviFinale[] = $result;
			}
			$this->cache[$keyid]['active_plugins'] = $pluginAttiviFinale;

			#endregion

			return $pluginAttiviFinale;
		}

		private function CercaUnMatchConUrlPagina()
		{
			$keys = array();
			//Dai la priorità all'URL generico
			//foreach (array( 'amp', 'url_1', 'url_2', 'url_3' ) as $key_UrlOrTipology) {
			foreach (array('amp') as $key_UrlOrTipology)
			{
				if (!empty($filter['urlkey'][$key_UrlOrTipology]))
				{
					$keys[$key_UrlOrTipology] = $filter['urlkey'][$key_UrlOrTipology];
				}
			}

			$ar_key = (!empty($filter['urlkeylist'])) ? array_filter(array_map("trim", explode(PHP_EOL, $filter['urlkeylist']))) : array();
			foreach ($ar_key as $key_UrlOrTipology)
			{
				$keys[$key_UrlOrTipology] = $key_UrlOrTipology;
			}

			$keys['wp-json'] = 'wp-json';
			$keys['heartbeat'] = 'admin-ajax';
			$keys['admin-ajax'] = 'admin-ajax';


			foreach ($keys as $key_UrlOrTipology => $kwd)
			{
//				$urlkey ="";

				if (preg_match("#([/&.?=]){$kwd}([/&.?=]|$)#u", $_SERVER['REQUEST_URI']))
				{
					if ($this->CondizioniDiEsclusione($key_UrlOrTipology))
						continue;
					else
						$urlkey = $key_UrlOrTipology;

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

		private function getArray_map($stringa): array
		{
			return array_map("trim", explode(',', $stringa));
		}


		private function CheckIfPluginIsToLoad($pluginAttivoCorrente, string $mobileOrDesktop)
		{
			global $wp_query;
			$filter = $this->filter;

			$unload = false;

//			$pluginAttivoCorrente = $pluginAttivoCorrente2;

			//admin mode filter
			if (!empty($filter['_admin']['plugins']))
			{
				//in_array — Checks if a value exists in an array
				//in_array ( mixed $needle , array $haystack [, bool $strict = FALSE ] ) : bool
				if (in_array($pluginAttivoCorrente, $this->getArray_map($filter['_admin']['plugins'])))
					$unload = true;
			}

			//page filter
			if (!$unload)
			{
				if (!empty($filter['_pagefilter']['plugins']))
				{
					if (in_array($pluginAttivoCorrente, $this->getArray_map($filter['_pagefilter']['plugins'])))
					{
						$unload = true;

						//desktop/mobile device disable filter
						$disabilitaPerQuestoDevice = true;


						if (is_singular() && is_object($wp_query->post))
						{
							$specificPagePostMeta_option = get_post_meta($wp_query->post->ID, '_plugin_load_filter', true);

							$defaultBehaviour = array('filter' => 'default',
								'desktop' => '',
								'mobile' => '');
							$pageBehaviourMobileOrDesktop = (!empty($specificPagePostMeta_option)) ? $specificPagePostMeta_option : $defaultBehaviour;
							$pageBehaviourMobileOrDesktop = wp_parse_args($pageBehaviourMobileOrDesktop, $defaultBehaviour);
							if ($pageBehaviourMobileOrDesktop['filter'] === 'include')
							{
								$pageBehaviour = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
								//strpos ( string $haystack , mixed $needle [, int $offset = 0 ] ) : int
								if (false !== strpos($pageBehaviour, $pluginAttivoCorrente))
									$disabilitaPerQuestoDevice = false;
							}
						}

						$filtriPerGruppi = $filter['group'];
						if (empty($pageBehaviourMobileOrDesktop) || $pageBehaviourMobileOrDesktop['filter'] === 'default')
						{
							if (!empty($filtriPerGruppi[$mobileOrDesktop]['plugins']))
							{
								if (false !== strpos($filtriPerGruppi[$mobileOrDesktop]['plugins'], $pluginAttivoCorrente))
									$disabilitaPerQuestoDevice = false;
							}
						}
						if ($disabilitaPerQuestoDevice)
						{
						} else
						{
							//oEmbed Content API
							if (is_embed())
							{
								$post_format = 'content-card';
								if (!empty($filtriPerGruppi[$post_format]['plugins']))
								{
									if (false !== strpos($filtriPerGruppi[$post_format]['plugins'], $pluginAttivoCorrente))
										$unload = false;
								}
							} else
							{
								$pageFormatOptions = false;

								if (is_singular())
								{
									if (!empty($pageBehaviourMobileOrDesktop) && $pageBehaviourMobileOrDesktop['filter'] === 'include')
									{
										$pageFormatOptions = true;
										$MobileOrDesktop = $pageBehaviourMobileOrDesktop[$mobileOrDesktop];
										if (false !== strpos($MobileOrDesktop, $pluginAttivoCorrente))
										{
											$unload = false;
										}
									}
								}
								if ($pageFormatOptions === false)
								{
									$post_format = $this->CalculatePostFormat($wp_query);

									if (!empty($post_format) && is_string($post_format) && !empty($filtriPerGruppi[$post_format]['plugins']))
									{
										if (in_array($pluginAttivoCorrente, array_map("trim", explode(',', $filtriPerGruppi[$post_format]['plugins']))))
											$unload = false;
									}
								}
							}
						}
					}
				}
			}
			if (!$unload) //Sopra c'è lo stesso if...
			{
				return $pluginAttivoCorrente;
			}
		}

		private function CaricaPluginAttivi(bool &$shouldReturn)
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

		/**
		 * @param $wp_query
		 * @return bool|false|string
		 */
		private function CalculatePostFormat($wp_query)
		{
			$post_format = false;
			if (is_home() || is_front_page())
				$post_format = 'home';
			elseif (is_archive())
				$post_format = 'archive';
			elseif (is_search())
				$post_format = 'search';
			elseif (is_attachment())
				$post_format = 'attachment';
			elseif (is_page())
				$post_format = 'page';
			elseif (is_single())
			{
				//Post & Custom Post
				$post_format = get_post_type($wp_query->post);
				if ($post_format === false && isset($wp_query->query_vars['post_type']))
				{
					$post_format = $wp_query->query_vars['post_type'];
				}
				if ($post_format === 'post')
				{
					$post_format_wp = get_post_format($wp_query->post);
					$post_format = ($post_format_wp === 'standard' || $post_format_wp == false) ? 'post' : "post-$post_format_wp";
				}
			}
			return $post_format;
		}

		private function GetAllPostsWithShortcodes()
        {
            $sql = <<<TAG
SELECT id  
FROM `wp_posts` 
WHERE `post_content` REGEXP '\\[.*\\]' 
AND  post_status = 'publish'
AND post_type = 'post'
TAG;

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

	}

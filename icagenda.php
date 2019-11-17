<?php
/**
 * @package    JLSitemap - ICAgenda Plugin
 * @version    1.0.0
 * @author     Andreas Michler - michler-solutions.de
 * @copyright  Copyright (c) 2019 michler-solutions. All rights reserved.
 * @license    GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link       https://michler-solutions.de/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

class plgJLSitemapiCagenda extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var boolean
	 *
	 * @since 1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Method to get urls array
	 *
	 * @param array    $urls   Urls array
	 * @param Registry $config Component config
	 *
	 * @return array Urls array with attributes
	 *
	 * @since 1.0.0
	 */
	public function onGetUrls(&$urls, $config)
	{
		$iCagendaExcludeStates = array(
			0  => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT_UNPUBLISH'),
			-2 => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT_TRASH'),
			2  => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT_ARCHIVE'));

		$multilanguage = $config->get('multilanguage');

		// Categories
		if ($this->params->get('events_enable', false))
		{
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select(array('a.id', 'a.title', 'a.alias', 'a.state', 'a.modified', 'a.enddate'))
				->from($db->quoteName('#__icagenda_events', 'a'))
                ->where($db->quoteName('state') . '=1')
				->order($db->escape('a.ordering') . ' ' . $db->escape('asc'));


			$db->setQuery($query);
			$rows = $db->loadObjectList();

			$nullDate   = $db->getNullDate();
			$nowDate    = Factory::getDate()->toUnix();
			$changefreq = $this->params->get('icagenda_changefreq', $config->get('changefreq', 'weekly'));
			$priority   = $this->params->get('icagenda_priority', $config->get('priority', '0.5'));

			// Add events to arrays
            $events   = array();
            $globalconfig = JFactory::getConfig();

			foreach ($rows as $row)
			{
				// Prepare loc attribute
                $slug = $row->id . '-' . $row->alias . '.html';
                $menuid = $this->params->get('events_root_menu', '1');

               
                $mode = $globalconfig->get('force_ssl', 0) == 2 ? 1 : (-1);
                $menuurl = JRoute::_("index.php?Itemid={$menuid}");

                $re = '/([^\/.]+)$|([^\/]+)(\.[^\/.]+)$/m';
                $url ='/';
                preg_match($re, $menuurl, $matches);
                for ($i = 1; $i < count($matches)-1; $i++) {
                    $url .= $matches[$i];
                }
                
				$loc  = $url . '/' .  $slug;

				// Prepare exclude attribute
				$metadata = new Registry($row->metadata);
                $exclude  = array();
                
                if (preg_match('/noindex/', $metadata->get('robots', $config->get('siteRobots'))))
				{
					$exclude[] = array('type' => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT'),
					                   'msg'  => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT_ROBOTS'));
				}
				if ($row->enddate == $nullDate || Factory::getDate($row->enddate)->toUnix() < $nowDate)
				{
					$exclude[] = array('type' => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT'),
					                   'msg'  => Text::_('PLG_JLSITEMAP_ICAGENDA_EXCLUDE_EVENT_ENDED'));
				}

				// Prepare lastmod attribute
				$lastmod = (!empty($row->modified) && $row->modified != $nullDate) ? $row->modified : false;

				// Prepare event object
				$event               = new stdClass();
				$event->type       = Text::_('PLG_JLSITEMAP_ICAGENDA_TYPES_EVENTS');
				$event->title      = $row->title;
				$event->loc        = $loc;
				$event->changefreq = $changefreq;
				$event->priority   = $priority;
				$event->lastmod    = $lastmod;
				$event->exclude    = (!empty($exclude)) ? $exclude : false;
				$event->alternates =  false;

				// Add event to array
				$events[] = $event;


			}
			// Add events to urls
			$urls = array_merge($urls, $events);
		}

		return $urls;
	}
}
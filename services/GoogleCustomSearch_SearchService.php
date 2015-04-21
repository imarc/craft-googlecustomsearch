<?php

namespace Craft;

class GoogleCustomSearch_SearchService extends BaseApplicationComponent
{
	/**
	 * Perform a site search
	 *
	 * Returns an object with the following properties:
	 *
	 *   page
	 *   start
	 *   end
	 *   total_guess
	 *   has_more
	 *   suggestion
	 *   results
	 *
	 *
	 * @param string query The search query
	 * @param string page The page to return
	 * @param string per_page How many results to dispaly per page
	 * @return object
	 **/
	public function performSearch($terms, $page, $per_page)
	{
		$settings = craft()->plugins->getPlugin('googlecustomsearch')->getSettings();

		
	}
}
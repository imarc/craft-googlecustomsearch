<?php
namespace Craft;

class GoogleCustomSearchVariable
{
	public function performSearch($terms, $page=1, $per_page=15)
	{
		return craft()->googleCustomSearch_search->performSearch($terms, $page, $per_page);
	}
}
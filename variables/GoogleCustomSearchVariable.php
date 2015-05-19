<?php
namespace Craft;

class GoogleCustomSearchVariable
{
    public function performSearch($terms, $page=1, $per_page=10, $extra=array())
    {
        return craft()->googleCustomSearch_search->performSearch($terms, $page, $per_page, $extra);
    }
}
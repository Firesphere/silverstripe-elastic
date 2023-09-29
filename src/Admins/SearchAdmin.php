<?php
namespace Firesphere\ElasticSearch\Admins;

use Firesphere\ElasticSearch\Models\SearchSynonym;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \Firesphere\ElasticSearch\Admins\SearchAdmin
 *
 */
class SearchAdmin extends ModelAdmin
{

    /**
     * @var string Add a pretty magnifying glass to the sidebar menu
     */
    private static $menu_icon_class = 'font-icon-search';

    /**
     * @var string Where to find me
     */
    private static $url_segment = 'searchadmin';

    /**
     * @var string My name
     */
    private static $menu_title = 'Search';

    /**
     * List of all managed {@link DataObject}s in this interface. {@link ModelAdmin::$managed_models}
     *
     * @config
     * @var array|string
     */
    private static $managed_models = [
        SearchSynonym::class
    ];
}

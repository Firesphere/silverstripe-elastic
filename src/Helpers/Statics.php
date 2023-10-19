<?php
/**
 * class Statics|Firesphere\ElasticSearch\Helpers\Statics Static helper
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Helpers;

use Firesphere\SearchBackend\Helpers\Statics as CoreStatics;
use SilverStripe\Core\Config\Configurable;

/**
 * Class Statics
 *
 * An extension to the core statics, to get the Elastic specific
 * typemap from the core
 */
class Statics extends CoreStatics
{
    use Configurable;
}

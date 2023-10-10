<?php

namespace Firesphere\ElasticSearch\Traits\QueryTraits;

use Minimalcode\Search\Criteria;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;

/**
 * @todo move to shared library
 */
trait QueryFilterTrait
{

    /**
     * Add filtering on view status
     */
    protected function getViewStatusFilter(): array
    {
        // Filter by what the user is allowed to see
        $viewIDs = ['null']; // null is always an option as that means publicly visible
        $member = Security::getCurrentUser();
        if ($member && $member->exists()) {
            // Member is logged in, thus allowed to see these
            $viewIDs[] = 'LoggedIn';

            /** @var DataList|Group[] $groups */
            $groups = Security::getCurrentUser()->Groups();
            if ($groups->count()) {
                $viewIDs = array_merge($viewIDs, $groups->column('Code'));
            }
        }

        return $viewIDs;
    }

}

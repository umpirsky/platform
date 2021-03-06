<?php

namespace Oro\Bundle\FlexibleEntityBundle\Grid\Extension\Filter;

use Oro\Bundle\FilterBundle\Form\Type\Filter\DateTimeRangeFilterType;

class FlexibleDateTimeRangeFilter extends AbstractFlexibleDateFilter
{
    /**
     * DateTime object as string format
     */
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * {@inheritdoc}
     */
    protected function getFormType()
    {
        return DateTimeRangeFilterType::NAME;
    }
}

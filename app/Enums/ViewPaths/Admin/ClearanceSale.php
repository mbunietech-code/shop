<?php

namespace App\Enums\ViewPaths\Admin;

enum ClearanceSale
{
    const LIST = [
        URI => '/',
        VIEW => 'admin-views.deal.clearance-sale.index'
    ];

    const VENDOR_OFFERS = [
        URI => 'vendor-offers',
        VIEW => 'admin-views.deal.clearance-sale.vendor-offers'
    ];
}

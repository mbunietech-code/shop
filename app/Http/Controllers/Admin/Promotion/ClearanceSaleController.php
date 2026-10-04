<?php

namespace App\Http\Controllers\Admin\Promotion;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\FlashDealRepositoryInterface;
use App\Enums\ViewPaths\Admin\ClearanceSale;
use App\Enums\ViewPaths\Admin\FeatureDeal;
use App\Http\Controllers\BaseController;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ClearanceSaleController extends BaseController
{
    public function __construct(
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
    )
    {
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        return $this->getListView($request);
    }

    public function getListView(Request $request): View
    {
        return view(ClearanceSale::LIST[VIEW]);
    }

    public function getVendorOffersView(Request $request): View
    {
        return view(ClearanceSale::VENDOR_OFFERS[VIEW]);
    }

}

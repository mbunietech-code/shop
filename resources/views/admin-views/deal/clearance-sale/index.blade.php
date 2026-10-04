@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Session;
@endphp
@extends('layouts.back-end.app')

@section('title', translate('Clearance_Sale'))

@section('content')
    @php($direction = Session::get('direction'))
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{dynamicAsset(path: 'public/assets/back-end/img/note.png')}}" alt="">
                {{ translate('Clearance_Sale') }}
            </h2>
        </div>
        @include('admin-views.deal.clearance-sale.clearance-sale-inline-menu')

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-center">
                    <div class="col-md-8 col-xl-9">
                        <h3>{{translate('Active clearance sale Offer ?')}}</h3>
                        <p class="m-0">*{{translate('By turning on maintenance mode Control your all system & function')}}</p>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <h5 class="mb-0 font-weight-normal">{{translate('Active Offer')}}</h5>
                            <label class="switcher ml-auto mb-0">
                                <input type="checkbox" class="switcher_input">
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="">Setup Offer Logics</h3>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label title-color font-weight-medium">{{translate('Duration')}}</label>
                        <div class="position-relative">
                            <span class="tio-calendar icon-absolute-on-right"></span>
                            <input type="text" class="js-daterangepicker form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label title-color font-weight-medium">{{translate('Discount Type')}}</label>
                        <div class="form-control d-flex gap-2">
                            <div class="custom-control custom-radio flex-grow-1">
                                <input type="radio" class="custom-control-input" value="flat" name="discount_type" id="flat">
                                <label class="custom-control-label" for="flat">Flat discount </label>
                            </div>
                            <div class="custom-control custom-radio flex-grow-1">
                                <input type="radio" class="custom-control-input" value="product" name="discount_type" id="product" checked>
                                <label class="custom-control-label" for="product">Product wise discount</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label title-color font-weight-medium">{{translate('Discount Amount')}} (%)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Ex : 10" placeholder="">
                            <div class="input-group-append">
                                <select name="" id="" class="form-control js-select2-custom">
                                    <option value="">%</option>
                                    <option value="">$</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label title-color font-weight-medium">{{translate('Offer Active Time')}}</label>
                        <div class="form-control d-flex gap-2">
                            <div class="custom-control custom-radio flex-grow-1">
                                <input type="radio" class="custom-control-input" value="always" name="activeTime" id="always" checked>
                                <label class="custom-control-label" for="always">Always</label>
                            </div>
                            <div class="custom-control custom-radio flex-grow-1">
                                <input type="radio" class="custom-control-input" value="specificTime" name="activeTime" id="specificTime">
                                <label class="custom-control-label" for="specificTime">Specific Time in a Day</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label title-color font-weight-medium">{{translate('Offer Active Time')}}</label>
                        <label class="form-check form--check form--check--inline border rounded py-2 min-h-40px">
                            <span class="user-select-none form-check-label flex-grow-1">
                                Also Show in Home Page
                                <span data-toggle="tooltip" data-placement="top" title="info">
                                    <i class="tio-info-outined"></i>
                                </span>
                            </span>
                            <input type="checkbox" name="show_in_home_page" class="form-check-input" checked>
                        </label>
                    </div>
                    <div class="col-md-6 ml-auto">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
                        <div class="btn--container justify-content-end">
                            <button class="btn btn-secondary" type="reset">{{ translate('Reset') }}</button>
                            <button class="btn btn--primary" type="submit">{{ translate('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <h4 class="m-0">Product List</h4>
                <a href="#product-add-modal" data-toggle="modal" class="btn btn--primary">+ {{ translate('Add Product') }}</a>
            </div>
            <div class="card-body">
                <div class="p-4 bg-chat rounded text-center">
                    <div class="py-5">
                        <img src="{{dynamicAsset('/public/assets/back-end/img/empty-product.png')}}" width="64" alt="">
                        <div class="mx-auto my-3 max-w-353px">
                            {{translate('Add Product show in the clearance offer section in customer app & website')}}
                        </div>
                        <a href="#product-add-modal" data-toggle="modal" class="text-primary text-underline">+ {{translate('Add Product')}}</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h4 class="m-0">Product List</h4>
                <div class="d-flex flex-wrap justify-content-end gap-3">
                    <form action="" method="GET">
                        <div class="input-group input-group-custom input-group-merge">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <i class="tio-search"></i>
                                </div>
                            </div>
                            <input id="datatableSearch_" type="search" name="searchValue" class="form-control" placeholder="Search by Order ID" aria-label="Search by Order ID" value="">
                            <button type="submit" class="btn btn--primary input-group-text">Search</button>
                        </div>
                    </form>
                    <a href="#product-add-modal" data-toggle="modal" class="btn btn--primary">+ {{ translate('Add Product') }}</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                        <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>SL</th>
                                <th>
                                    <div class="d-flex">
                                        <div class="w-60px">Image</div>
                                        <div>Name</div>
                                    </div>
                                </th>
                                <th class="text-center">Unit Price($)</th>
                                <th class="text-center">Discount Amount</th>
                                <th class="text-center">Discount Price($)</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>5</td>
                                <td>
                                    <a href="" class="title-color hover-c1 d-flex align-items-center gap-10">
                                        <img src="{{asset('/public/assets/back-end/img/160x160/img2.jpg')}}" class="rounded" alt="" width="60">
                                        <div class="max-w-200">
                                            <h6>
                                                {{Str::limit('Family Size Trolley Case Long Lasting and 8 Wheel Waterproof bag', 41)}}
                                            </h6>
                                            <div class="fs-12">
                                                <div>
                                                    <span>Current Stock:</span><span>10</span>
                                                </div>
                                                <div>
                                                    <span>Category:</span><span>Travel</span>
                                                    <span>Brand:</span><span>i Bird</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <div class="text-center">
                                        500.27
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <input type="number" class="form-control w-60px h-25 text-center px-1" value="50">
                                        <button type="button" class="btn text-primary">
                                            <i class="tio-edit"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        500.27
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center">
                                        <label class="switcher mx-auto">
                                            <input type="checkbox" class="switcher_input">
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center">
                                        <a title="Delete" class="btn btn-outline-danger square-btn" href="javascript:">
                                            <i class="tio-delete"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal fade" id="product-add-modal">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="border-bottom">
                            <h4>{{translate('Add Product')}}</h4>
                            <p>
                                {{translate('Search product & add to your clearance list')}}
                            </p>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">{{translate('Products')}}</label>
                            <div class="position-relative">
                                <input type="text" class="form-control pl-5" placeholder="{{translate('Search Product')}}">
                                <span class="tio-search position-absolute left-0 top-0 h-42px d-flex align-items-center pl-2"></span>
                            </div>
                        </div>
                        <div class="p-4 bg-chat rounded text-center mt-3">
                            <img src="{{dynamicAsset('/public/assets/back-end/img/empty-product.png')}}" width="64" alt="">
                            <div class="mx-auto my-3 max-w-353px">
                                {{translate('Add Product show in the clearance offer section in customer app & website')}}
                            </div>
                            <a href="#product-add-modal" data-toggle="modal" class="text-primary text-underline">+ {{translate('Add Product')}}</a>
                        </div>
                        <div class="btn--container justify-content-end mt-3">
                            <button class="btn btn-secondary" type="reset">{{ translate('Reset') }}</button>
                            <button class="btn btn--primary" type="submit">{{ translate('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('script')
    <script src="{{dynamicAsset(path: 'public/assets/back-end/js/admin/deal.js') }}"></script>
@endpush

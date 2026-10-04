@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Session;
@endphp
@extends('layouts.back-end.app')

@section('title', translate('Vendor_Offers'))

@section('content')
    @php($direction = Session::get('direction'))
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{dynamicAsset(path: 'public/assets/back-end/img/note.png')}}" alt="">
                {{ translate('Vendor_Offers') }}
            </h2>
        </div>
        @include('admin-views.deal.clearance-sale.clearance-sale-inline-menu')

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-center">
                    <div class="col-md-8 col-xl-9">
                        <h3>{{translate('Show Clearance Offer in Home Page')}}</h3>
                        <p class="m-0">{{translate('You can highlight all clearance offer products in home page to increase customer reach')}}</p>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <h5 class="mb-0 font-weight-normal">{{translate('Show Offer in home page ?')}}</h5>
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
                <div class="border-bottom">
                    <h3>{{translate('Add Vendor')}}</h3>
                    <p>
                        {{translate('Alongside with your in-house product , you can highlight vendor’s product who has activate their clearance offer.')}}
                    </p>
                </div>
                <div class="mt-3">
                    <div class="position-relative">
                        <input type="text" class="form-control pl-5" placeholder="{{translate('Search Vendors')}}">
                        <span class="tio-search position-absolute left-0 top-0 h-42px d-flex align-items-center pl-2"></span>
                    </div>
                </div>
                <div class="p-4 bg-chat rounded text-center mt-3">
                    <div class="py-5">
                        <img src="{{dynamicAsset('/public/assets/back-end/img/empty-store.png')}}" width="58" alt="">
                        <div class="mx-auto my-3 max-w-353px">
                            {{translate('No vendors are added')}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h3 class="mb-3">{{translate('Vendor List')}}</h3>
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
                                        <img src="{{asset('/public/assets/back-end/img/160x160/img2.jpg')}}" class="rounded" alt="" width="50">
                                        <div class="max-w-200">
                                            <h6>
                                                {{Str::limit('Morning Mart')}}
                                            </h6>
                                            <div class="fs-12">
                                                <span>(273 Review)</span> <span> <i class="tio-star text-F5A200"></i> 4.5</span>
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

    </div>
@endsection

@push('script')
    <script src="{{dynamicAsset(path: 'public/assets/back-end/js/admin/deal.js') }}"></script>
@endpush

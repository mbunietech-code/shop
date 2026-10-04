@extends('layouts.back-end.app')
@section('title', 'ClickPesa Transactions')

@section('content')
    <div class="content container-fluid">
        <div class="mb-4 pb-2">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/3rd-party.png') }}" alt="">
                ClickPesa Transactions
            </h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-5 mb-2">
                            <input type="search" name="searchValue" class="form-control"
                                   placeholder="Search order, phone, transaction, reference, control number"
                                   value="{{ $search }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="status" class="form-control">
                                @foreach(['all', 'pending', 'processing', 'paid', 'failed', 'cancelled', 'expired', 'review_required'] as $item)
                                    <option value="{{ $item }}" {{ $status === $item ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $item)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button class="btn btn--primary btn-block" type="submit">{{ translate('search') }}</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Control Number</th>
                            <th>ClickPesa Reference</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($transactions as $transaction)
                            @php($payer = json_decode($transaction->payer_information ?: '{}', true) ?: [])
                            <tr>
                                <td>{{ \Illuminate\Support\Str::limit($transaction->id, 12) }}</td>
                                <td>{{ $payer['name'] ?? '-' }}</td>
                                <td>{{ $transaction->payment_phone ?? '-' }}</td>
                                <td>{{ $transaction->currency_code }} {{ number_format((float)$transaction->payment_amount, 2) }}</td>
                                <td>{{ $transaction->provider_payment_method ?? $transaction->payment_method ?? '-' }}</td>
                                <td>{{ $transaction->control_number ?? '-' }}</td>
                                <td>{{ $transaction->provider_reference ?? '-' }}</td>
                                <td>{{ $transaction->provider_transaction_id ?? $transaction->transaction_id ?? '-' }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $transaction->payment_status ?? ($transaction->is_paid ? 'paid' : 'pending'))) }}</td>
                                <td>{{ optional($transaction->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('admin.business-settings.payment-transactions.clickpesa.show', $transaction->id) }}">
                                        {{ translate('view') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center">{{ translate('no_data_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

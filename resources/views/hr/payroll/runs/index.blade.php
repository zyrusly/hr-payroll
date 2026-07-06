@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title"><h1><i class="icon-calculator"></i> {{ __('Payroll Runs') }}</h1></div>
    @include('partials.flash')
    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    @if($canGeneratePayroll ?? false)
                        <h5 class="table_banner_title mb-2">{{ __('Generate Payroll Draft') }}</h5>
                        <form method="POST" action="{{ route('payroll.runs.generate') }}" class="row g-2 mb-4">
                            @csrf
                            <div class="col-md-2"><select name="pay_frequency" class="form-control" required><option value="monthly">{{ __('Monthly') }}</option><option value="weekly">{{ __('Weekly') }}</option></select></div>
                            <div class="col-md-3"><select name="employee_id" class="form-control js-example-basic-single"><option value="0">{{ __('All Active Employees') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
                            <div class="col-md-2"><input type="text" name="period_label" class="form-control" value="{{ now()->format('M Y') }}" placeholder="{{ __('Period label') }}"></div>
                            <div class="col-md-2"><input type="text" name="period_start" class="form-control datetimepicker" value="{{ now()->startOfMonth()->toDateString() }}" required></div>
                            <div class="col-md-2"><input type="text" name="period_end" class="form-control datetimepicker" value="{{ now()->endOfMonth()->toDateString() }}" required></div>
                            <div class="col-md-1"><button class="btn btn-custom w-100" type="submit" title="{{ __('Generate Draft') }}"><i class="icon-plus"></i></button></div>
                            <div class="col-md-2"><input type="text" name="pay_date" class="form-control datetimepicker" placeholder="{{ __('Pay date') }}"></div>
                            <div class="col-md-2"><input type="number" min="1" max="53" name="payroll_week" class="form-control" placeholder="{{ __('Week') }}"></div>
                        </form>
                    @endif

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-2"><select name="status" class="form-control"><option value="">{{ __('All Status') }}</option>@foreach(['draft','processed','approved','paid'] as $status)<option value="{{ $status }}" {{ $filters['status']===$status?'selected':'' }}>{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }} / page</option>@endforeach</select></div>
                        <div class="col-md-8 d-flex gap-2"><button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button><a href="{{ route('payroll.runs.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Frequency') }}</th><th>{{ __('Items') }}</th><th>{{ __('Gross') }}</th><th>{{ __('Deductions') }}</th><th>{{ __('Net') }}</th><th>{{ __('Status') }}</th><th>{{ __('Finalized By') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                            <tbody>
                                @forelse($runs as $run)
                                    <tr>
                                        <td>{{ $run->period_label ?: $run->period_start.' - '.$run->period_end }}<br><small class="text-muted">{{ $run->period_start }} to {{ $run->period_end }}</small></td>
                                        <td>{{ __(ucfirst($run->pay_frequency)) }}</td>
                                        <td>{{ $run->items_count }}</td>
                                        <td>{{ number_format((float)$run->gross_total, 2) }}</td>
                                        <td>{{ number_format((float)$run->deduction_total, 2) }}</td>
                                        <td>{{ number_format((float)$run->net_total, 2) }}</td>
                                        <td><span class="badge bg-secondary">{{ __(ucfirst($run->status)) }}</span></td>
                                        <td>{{ $run->processor?->name ?: '-' }}</td>
                                        <td class="action-buttons"><a href="{{ route('payroll.runs.show', $run) }}" title="{{ __('View') }}"><i class="icon-eye"></i></a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center">{{ __('No payroll runs found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $runs->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

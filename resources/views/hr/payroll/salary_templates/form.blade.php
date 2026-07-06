@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-wallet"></i> {{ $mode === 'edit' ? __('Edit Salary Template') : __('Add Salary Template') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('payroll.salary-templates.update', $template) : route('payroll.salary-templates.store') }}">
                        @csrf
                        @if($mode === 'edit') @method('PUT') @endif
                        <div class="row">
                            <div class="col-md-4 form-group mb-3"><label>{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" required></div>
                            <div class="col-md-2 form-group mb-3"><label>{{ __('Code') }}</label><input type="text" name="code" class="form-control" value="{{ old('code', $template->code ?? '') }}" maxlength="30" required></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Pay Frequency') }}</label><select name="pay_frequency" class="form-control" required>@foreach(['monthly','weekly'] as $frequency)<option value="{{ $frequency }}" {{ old('pay_frequency', $template->pay_frequency ?? 'monthly')===$frequency?'selected':'' }}>{{ __(ucfirst($frequency)) }}</option>@endforeach</select></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Status') }}</label><select name="is_active" class="form-control" required><option value="1" {{ (int)old('is_active', $template->is_active ?? 1)===1?'selected':'' }}>{{ __('Active') }}</option><option value="0" {{ (int)old('is_active', $template->is_active ?? 1)===0?'selected':'' }}>{{ __('Inactive') }}</option></select></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Basic Salary') }}</label><input type="number" step="0.01" min="0" name="basic_salary" class="form-control" value="{{ old('basic_salary', $template->basic_salary ?? '') }}" required></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('House Rent') }}</label><input type="number" step="0.01" min="0" name="house_rent" class="form-control" value="{{ old('house_rent', $template->house_rent ?? 0) }}"></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Medical Allowance') }}</label><input type="number" step="0.01" min="0" name="medical_allowance" class="form-control" value="{{ old('medical_allowance', $template->medical_allowance ?? 0) }}"></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Conveyance Allowance') }}</label><input type="number" step="0.01" min="0" name="conveyance_allowance" class="form-control" value="{{ old('conveyance_allowance', $template->conveyance_allowance ?? 0) }}"></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Other Allowance') }}</label><input type="number" step="0.01" min="0" name="other_allowance" class="form-control" value="{{ old('other_allowance', $template->other_allowance ?? 0) }}"></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Provident Fund %') }}</label><input type="number" step="0.01" min="0" max="100" name="provident_fund_percent" class="form-control" value="{{ old('provident_fund_percent', $template->provident_fund_percent ?? 0) }}"></div>
                            <div class="col-md-3 form-group mb-3"><label>{{ __('Tax %') }}</label><input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control" value="{{ old('tax_percent', $template->tax_percent ?? 0) }}"></div>
                            <div class="col-md-12 form-group mb-3"><label>{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="3">{{ old('notes', $template->notes ?? '') }}</textarea></div>
                        </div>
                        <button class="btn btn-custom" type="submit"><i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i> {{ $mode === 'edit' ? __('Update Template') : __('Create Template') }}</button>
                        <a href="{{ route('payroll.salary-templates.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

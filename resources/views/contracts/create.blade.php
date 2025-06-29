@php
    $plan = App\Models\Utility::getChatGPTSettings();
@endphp

{{ Form::open(array('url' => 'contract', 'class' => 'needs-validation', 'novalidate')) }}
<div class="modal-body">

    @if ($plan->enable_chatgpt == 'on')
    <div class="text-end">
        <a href="#" class="btn btn-sm btn-primary" data-size="medium" data-ajax-popup-over="true" data-url="{{ route('generate', ['contract']) }}"
            data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Generate') }}"
            data-title="{{ __('Generate Content With AI') }}">
            <i class="fas fa-robot"></i>{{ __(' Generate With AI') }}
        </a>
    </div>
    @endif

    <div class="row">
        
        <div class="col-md-6 form-group">
            {{ Form::label('employee_name', __('Employee Name'),['class'=>'col-form-label']) }}<x-required></x-required>
            {{ Form::select('employee_name', $employee,null, array('class' => 'form-control select2','required'=>'required')) }}
        </div>
        <div class="col-md-6 form-group">
            {{ Form::label('subject', __('Subject'),['class'=>'col-form-label']) }}<x-required></x-required>
            {{ Form::text('subject', '', array('class' => 'form-control','required'=>'required')) }}
        </div>
        <div class="col-md-6 form-group">
            {{ Form::label('value', __('Value'),['class'=>'col-form-label']) }}<x-required></x-required>
            {{ Form::number('value', '', array('class' => 'form-control','required'=>'required','min' => '1')) }}
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('type', __('Type'),['class'=>'col-form-label']) }}<x-required></x-required>
                {{ Form::select('type', $contractType,null, array('class' => 'form-control select2','required'=>'required')) }}
                @if(count($contractType) <= 0)
                    <div class="text-muted text-xs">
                        {{__('Please create new contract type')}} <a href="{{route('contract_type.index')}}">{{__('here')}}</a>.
                    </div>
                @endif
            </div>
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('start_date', __('Start Date'),['class'=>'col-form-label']) }}<x-required></x-required>
            {{ Form::date('start_date', null, array('class' => 'form-control current_date','required'=>'required')) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('end_date', __('Due Date'),['class'=>'col-form-label']) }}<x-required></x-required>
            {{ Form::date('end_date', null, array('class' => 'form-control current_date','required'=>'required')) }}
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('description', __('Description'),['class'=>'col-form-label']) }}
                {{ Form::textarea('description', '', array('class' => 'form-control', 'rows' => '3')) }}
            </div>
        </div>
    </div>
</div>


<div class="modal-footer">
    <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn  btn-primary">{{__('Create')}}</button>
   
</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        var now = new Date();
        var month = (now.getMonth() + 1);
        var day = now.getDate();
        if (month < 10) month = "0" + month;
        if (day < 10) day = "0" + day;
        var today = now.getFullYear() + '-' + month + '-' + day;
        $('.current_date').val(today);
    });
</script>
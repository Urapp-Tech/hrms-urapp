{{ Form::open(['url' => 'shift', 'method' => 'post', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">

    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('name', __('Shift Name'), ['class' => 'col-form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Shift Name')]) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('start_time', __('Start Time'), ['class' => 'col-form-label']) }}<x-required></x-required>
            {{ Form::time('start_time', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('end_time', __('End Time'), ['class' => 'col-form-label']) }}<x-required></x-required>
            {{ Form::time('end_time', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

    </div>

</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        // Add any necessary JavaScript here, if required
    });
</script>

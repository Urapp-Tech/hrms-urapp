{{ Form::model($shift, ['route' => ['shift.update', $shift->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">

    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('name', __('Shift Name'), ['class' => 'col-form-label']) }}
            {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Enter Shift Name'), 'required' => 'required']) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('start_time', __('Start Time'), ['class' => 'col-form-label']) }}
            {{ Form::time('start_time', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('end_time', __('End Time'), ['class' => 'col-form-label']) }}
            {{ Form::time('end_time', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>

    </div>

</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        // Any specific JS logic can be added here if needed
    });
</script>

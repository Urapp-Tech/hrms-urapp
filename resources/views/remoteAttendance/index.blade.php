@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Remote Attendance Permissions') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Remote Attendance') }}</li>
@endsection

@section('action-button')
    @can('Create Remote Attendance')
        <a href="#" data-url="{{ route('remote-attendance.create') }}" data-ajax-popup="true"
            data-title="{{ __('Create New Remote Attendance Permission') }}" data-bs-toggle="tooltip" title=""
            data-size="lg" class="btn btn-sm btn-primary" data-bs-original-title="{{ __('Create') }}">
            <i class="ti ti-plus"></i>
        </a>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table" id="pc-dt-simple">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th width="200px">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($remote_attendancePermissions as $permission)
                                    <tr>
                                        <td>{{ $permission->employee->name }}</td>
                                        <td>{{ \Carbon\Carbon::parse($permission->start_date)->format('d M Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($permission->end_date)->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge bg-{{ $permission->status == 'approved' ? 'success' : ($permission->status == 'pending' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($permission->status) }}
                                            </span>
                                        </td>
                                        <td class="Action">
                                            <span>
                                                @can('Edit Remote Attendance')
                                                    <div class="action-btn bg-info ms-2">
                                                        <a class="mx-3 btn btn-sm align-items-center"
                                                            data-url="{{ route('remote-attendance.edit', $permission->id) }}"
                                                            data-ajax-popup="true" data-size="lg" data-bs-toggle="tooltip"
                                                            title="" data-title="{{ __('Edit Remote Attendance Permission') }}"
                                                            data-bs-original-title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan

                                                @can('Delete Remote Attendance')
                                                    <div class="action-btn bg-danger ms-2">
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['remote-attendance.destroy', $permission->id],
                                                            'id' => 'delete-form-' . $permission->id,
                                                        ]) !!}
                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para"
                                                            data-bs-toggle="tooltip" title="Delete"
                                                            aria-label="Delete">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endcan
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

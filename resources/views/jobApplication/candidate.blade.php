@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Archive Application') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>

    <li class="breadcrumb-item">{{ __('Archive Application') }}</li>
@endsection

@section('content')
    <div class="row">

        <div class="col-xl-12">
            <div class="card">
                <div class="card-header card-body table-border-style">
                    {{-- <h5> </h5> --}}
                    <div class="table-responsive">
                        <table class="table" id="pc-dt-simple">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Applied For') }}</th>
                                    <th>{{ __('Rating') }}</th>
                                    <th>{{ __('Applied at') }}</th>
                                    <th>{{ __('Resume') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($archive_application as $application)
                                    <tr>
                                        <td><a class="btn btn-outline-primary"
                                                href="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}">
                                                {{ $application->name }}</a></td>
                                        <td>{{ !empty($application->jobs) ? $application->jobs->title : '-' }}</td>
                                        <td>
                                            @for ($i = 1; $i <= 5; $i++)
                                                @if ($application->rating < $i)
                                                    @if (is_float($application->rating) && round($application->rating) == $i)
                                                        <i class="text-warning fas fa-star-half-alt"></i>
                                                    @else
                                                        <i class="fas fa-star"></i>
                                                    @endif
                                                @else
                                                    <i class="text-warning fas fa-star"></i>
                                                @endif
                                            @endfor
                                        </td>
                                        <td>{{ \Auth::user()->dateFormat($application->created_at) }}</td>
                                        <td>
                                            @php
                                                $resumes = \App\Models\Utility::get_file('uploads/job/resume');
                                            @endphp
                                            @if (!empty($application->resume))
                                                <span class="action-btn bg-primary ms-2">
                                                    <a class="mx-3 btn btn-sm align-items-center"
                                                        href="{{ $resumes . '/' . $application->resume }}"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-original-title="{{ __('Download') }}" download><i
                                                            class="ti ti-download text-white"></i></a>
                                                </span>
                                                <div class="action-btn bg-secondary ms-2">
                                                    <a class="mx-3 btn btn-sm align-items-center"
                                                        href="{{ $resumes . '/' . $application->resume }}" target="_blank">
                                                        <i class="ti ti-crosshair text-white" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Preview') }}"></i>
                                                    </a>
                                                </div>
                                            @else
                                                -
                                            @endif


                                        </td>
                                        <td>
                                            @can('Show Job Application')
                                                <div class="action-btn bg-info ms-2">

                                                    <a class="mx-3 btn btn-sm  align-items-center" data-bs-toggle="tooltip"
                                                        title="{{ __('View') }}" data-title="{{ __('Details') }}"
                                                        href="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}">
                                                        <i class="ti ti-eye text-white"></i></a>
                                                </div>
                                            @endcan


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

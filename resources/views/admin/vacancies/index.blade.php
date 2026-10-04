@extends('adminlte::page')

@php
    $title = "Positions";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp 

@section('title', config('app.name', '') . ' | ' . $title)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">{{ $title }}</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible auto-close">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            {{ session('status') }}
        </div>
    @endif

    <div class="container-fluid">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-0">Vacancy Management</h3>
                    <div class="small text-muted">Create, publish, schedule, close, and monitor recruitment vacancies.</div>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="{{ route('admin.vacancies.reports.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
                        <i class="fas fa-chart-bar mr-1"></i> Reports
                    </a>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.vacancies.create') }}">
                        <i class="fas fa-plus mr-1"></i> New Vacancy
                    </a>
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="get" action="{{ route('admin.vacancies.index') }}" class="form-row align-items-end">
                    <div class="form-group col-md-5 mb-2">
                        <label class="small mb-1">Search</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Position title or cycle" value="{{ $search ?? '' }}">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-1">Posting Status</label>
                        <select name="state" class="form-control form-control-sm">
                            <option value="">All statuses</option>
                            <option value="draft" {{ ($state ?? '')==='draft' ? 'selected' : '' }}>Draft</option>
                            <option value="scheduled" {{ ($state ?? '')==='scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="open" {{ ($state ?? '')==='open' ? 'selected' : '' }}>Open for Applications</option>
                            <option value="closed" {{ ($state ?? '')==='closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4 mb-2">
                        <button class="btn btn-sm btn-primary mr-1"><i class="fas fa-search mr-1"></i> Filter</button>
                        @if(($search ?? '') || ($state ?? ''))
                            <a href="{{ route('admin.vacancies.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Cycle</th>
                            <th>Office</th>
                            <th>Posting</th>
                            <th>Station Stage</th>
                            <th>Division Stage</th>
                            <th class="text-right">Applicants</th>
                            <th style="width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($vacancies as $vacancy)
                        @php
                            $posting = $vacancy->getPostingStatus();
                            $postingClass = $posting==='Open for Applications' ? 'success' : ($posting==='Scheduled' ? 'info' : ($posting==='Closed' ? 'secondary' : 'light border'));
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.applications.vacancy.show', $vacancy) }}"><strong>{{ $vacancy->position_title }}</strong></a>
                                <div class="small text-muted">SG {{ $vacancy->salary_grade }} · {{ $vacancy->vacancy }} {{ $vacancy->vacancy==1 ? 'vacancy' : 'vacancies' }}</div>
                            </td>
                            <td>{{ $vacancy->cycle }}</td>
                            <td>{{ $vacancy->getOffice() }}</td>
                            <td>
                                <span class="badge badge-{{ $postingClass }}">{{ $posting }}</span>
                                @if($vacancy->posting_end_at)
                                    <div class="small text-muted mt-1">Closes {{ $vacancy->posting_end_at->format('M d, Y h:i A') }}</div>
                                @elseif($vacancy->posting_start_at && $posting==='Scheduled')
                                    <div class="small text-muted mt-1">Opens {{ $vacancy->posting_start_at->format('M d, Y h:i A') }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-light border">{{ $vacancy->getLevel1Status() }}</span></td>
                            <td><span class="badge badge-light border">{{ $vacancy->getLevel2Status() }}</span></td>
                            <td class="text-right">
                                <strong>{{ $vacancy->applications_count }}</strong>
                                <div class="small text-muted">{{ $vacancy->applications_with_station_count }} assigned</div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.vacancies.edit', $vacancy) }}" class="btn btn-outline-primary" title="Edit vacancy">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown">
                                        <span class="sr-only">More actions</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a href="{{ route('admin.applications.vacancy.show', $vacancy) }}" class="dropdown-item">
                                            <i class="fas fa-users mr-2 text-muted"></i> View Applicants
                                        </a>
                                        <a href="{{ route('admin.vacancies.apply', $vacancy) }}" class="dropdown-item" target="_blank"
                                           onclick="return confirm('Add an applicant manually only when authorized by the HRMPSB Chair. Continue?')">
                                            <i class="fas fa-user-plus mr-2 text-muted"></i> Add Applicant Manually
                                        </a>
                                        <div class="dropdown-divider"></div>

                                        @if($posting==='Draft')
                                        <form method="post" action="{{ route('admin.vacancies.publish',$vacancy) }}">@csrf
                                            <button class="dropdown-item text-success" onclick="return confirm('Publish this vacancy and open applications now?')">
                                                <i class="fas fa-bullhorn mr-2"></i> Publish Now
                                            </button>
                                        </form>
                                        @elseif($posting==='Open for Applications')
                                        <form method="post" action="{{ route('admin.vacancies.close_posting',$vacancy) }}">@csrf
                                            <button class="dropdown-item text-warning" onclick="return confirm('Close applications for this vacancy now?')">
                                                <i class="fas fa-door-closed mr-2"></i> Close Applications
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.vacancies.draft',$vacancy) }}">@csrf
                                            <button class="dropdown-item" onclick="return confirm('Return this vacancy to Draft? Posting dates will be preserved.')">
                                                <i class="fas fa-undo mr-2 text-muted"></i> Return to Draft
                                            </button>
                                        </form>
                                        @elseif($posting==='Closed')
                                        <form method="post" action="{{ route('admin.vacancies.reopen',$vacancy) }}">@csrf
                                            <button class="dropdown-item text-success" onclick="return confirm('Reopen applications now with no automatic closing date?')">
                                                <i class="fas fa-door-open mr-2"></i> Reopen Applications
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.vacancies.draft',$vacancy) }}">@csrf
                                            <button class="dropdown-item">
                                                <i class="fas fa-undo mr-2 text-muted"></i> Return to Draft
                                            </button>
                                        </form>
                                        @elseif($posting==='Scheduled')
                                        <form method="post" action="{{ route('admin.vacancies.publish',$vacancy) }}">@csrf
                                            <button class="dropdown-item text-success" onclick="return confirm('Override the schedule and open applications now?')">
                                                <i class="fas fa-play mr-2"></i> Open Now
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.vacancies.draft',$vacancy) }}">@csrf
                                            <button class="dropdown-item">
                                                <i class="fas fa-undo mr-2 text-muted"></i> Return to Draft
                                            </button>
                                        </form>
                                        @endif

                                        @if($vacancy->applications_count===0)
                                            <div class="dropdown-divider"></div>
                                            <a href="{{ route('admin.vacancies.delete',$vacancy) }}" class="dropdown-item text-danger">
                                                <i class="fas fa-trash mr-2"></i> Delete
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No vacancies found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                {{ $vacancies->links('pagination::simple-bootstrap-4') }}
            </div>
        </div>
    </div>
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
@stop

@section('plugins.Datatables', false)

@section('js')
    <script> console.log('Hi!'); </script>
@stop

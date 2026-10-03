@extends('adminlte::page')
@section('title','Assessment Content Bank')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">Assessment Content Bank</h1>
        <small class="text-muted">Reviewed written items and skills tasks with reuse/exposure history</small>
    </div>
    <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary">Assessment Center</a>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="card">
    <div class="card-body">
        <form method="get" class="form-row align-items-end">
            <div class="form-group col-md-5">
                <label>Search</label>
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Question, task, title">
            </div>
            <div class="form-group col-md-3">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="">All</option>
                    <option value="written_item" {{ request('type')==='written_item'?'selected':'' }}>Written items</option>
                    <option value="skill_task" {{ request('type')==='skill_task'?'selected':'' }}>Skills tasks</option>
                </select>
            </div>
            <div class="form-group col-md-2">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
                    <option value="retired" {{ request('status')==='retired'?'selected':'' }}>Retired</option>
                </select>
            </div>
            <div class="form-group col-md-2">
                <button class="btn btn-primary btn-block">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Type</th><th>Position</th><th>Content</th><th>Exposure</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td><span class="badge badge-{{ $item->content_type==='written_item'?'primary':'info' }}">{{ str_replace('_',' ',$item->content_type) }}</span></td>
                    <td>{{ optional($item->vacancy)->position_title ?: '-' }}</td>
                    <td style="min-width:360px">
                        @if($item->title)<strong>{{ $item->title }}</strong><br>@endif
                        {{ Str::limit($item->content,220) }}
                        @if($item->metadata)
                            <details class="small mt-1"><summary>Metadata</summary><pre class="text-wrap">{{ json_encode($item->metadata,JSON_PRETTY_PRINT) }}</pre></details>
                        @endif
                    </td>
                    <td>{{ number_format($item->usage_count) }}</td>
                    <td>{{ $item->retired_at ? 'Retired' : 'Active' }}</td>
                    <td>
                        @if($item->retired_at)
                            <form method="post" action="{{ route('admin.assessment_bank.restore',$item) }}">@csrf
                                <button class="btn btn-xs btn-outline-success">Restore</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.assessment_bank.retire',$item) }}" onsubmit="return confirm('Retire this bank item from future reuse?');">@csrf
                                <button class="btn btn-xs btn-outline-secondary">Retire</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No bank content yet. Approved content is added automatically.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="card-footer">{{ $items->links('pagination::bootstrap-4') }}</div>@endif
</div>
@stop

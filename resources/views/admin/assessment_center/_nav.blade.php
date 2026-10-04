@php
    $assessmentCenterActive = request()->routeIs('admin.assessment_center.*');
    $assessmentRoutes = request()->routeIs('admin.assessment_groups.*')
        || request()->routeIs('admin.skill_groups.*')
        || request()->routeIs('admin.assessments.*')
        || request()->routeIs('admin.skills.*');
    $qualityActive = request()->routeIs('admin.assessment_bank.*');
    $adminActive = request()->routeIs('admin.assessment_snapshots.*')
        || request()->routeIs('admin.assessment_permissions.*');
@endphp

<div class="card card-outline card-light mb-3 assessment-center-nav">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <nav class="nav nav-pills flex-wrap" aria-label="Assessment Center" style="gap:.25rem;">
                <a class="nav-link {{ $assessmentCenterActive ? 'active' : '' }}"
                   href="{{ route('admin.assessment_center.index') }}">
                    <i class="fas fa-home mr-1"></i> Overview
                </a>
                <a class="nav-link {{ $assessmentRoutes ? 'active' : '' }}"
                   href="{{ route('admin.assessment_center.index') }}#assessments">
                    <i class="fas fa-clipboard-list mr-1"></i> Assessments
                </a>
                <a class="nav-link {{ $qualityActive ? 'active' : '' }}"
                   href="{{ route('admin.assessment_bank.index') }}">
                    <i class="fas fa-shield-alt mr-1"></i> Content & Quality
                </a>
                <a class="nav-link"
                   href="{{ route('admin.assessment_center.index') }}#operationsHealth">
                    <i class="fas fa-heartbeat mr-1"></i> Operations
                </a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $adminActive ? 'active' : '' }}"
                       href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-cog mr-1"></i> Administration
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{ route('admin.assessment_snapshots.index') }}">
                            <i class="fas fa-camera mr-2 text-muted"></i> Snapshots
                        </a>
                        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1)
                        <a class="dropdown-item" href="{{ route('admin.assessment_permissions.index') }}">
                            <i class="fas fa-user-shield mr-2 text-muted"></i> Access Roles
                        </a>
                        @endif
                    </div>
                </div>
            </nav>

            <div class="small text-muted mt-2 mt-lg-0">
                Group → Set → Attempt
            </div>
        </div>
    </div>
</div>

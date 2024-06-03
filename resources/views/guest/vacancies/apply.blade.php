@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Vacancy Apply
@endsection

@section('css')
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Vacancy Apply</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>                        
                        <li class="breadcrumb-item"><a href="{{route('guest.vacancies.index')}}">Vacancies</a></li>
                        <li class="breadcrumb-item"><a href="{{route('guest.vacancies.show', $vacancy)}}">Details</a></li>
                        <li class="breadcrumb-item active">Apply</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            @error('email')
                <div class="alert alert-danger alert-dismissible auto-close">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    The system detected that you already applied for the same position; duplicate application is not allowed.
                </div>
            @enderror
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Applying for <strong>{{$vacancy->position_title}}</strong></h3>
                            <div class="card-tools">
                                <div class="btn-group">
                                    <a href="{{route('guest.vacancies.show', $vacancy)}}" class="btn btn-default btn-sm">
                                        <i class="fas fa-reply"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        <form method="post" action="{{route('guest.applications.store', $vacancy)}}">
                        @csrf
                        @method('post')
                        <div class="card-body">
                            <div class="callout callout-danger">
                                <small>
                                    All fields are required. Moverover, further modifications are no longer allowed once the 
                                    form has been submitted; thus, please carefully review the entries before submitting this form.
                                </small>
                            </div>
                            <div class="row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">First name & Ext name (if any)</label>
                                        <input type="text" class="form-control" placeholder="e.g. Juan Jr." 
                                            name="first_name" class="@error('first_name') is-invalid @enderror"
                                            value="{{ old('first_name') }}" autofocus>
                                        @error('first_name')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Middle name</label>
                                        <input type="text" class="form-control" placeholder="e.g. Luna or '-' if not applicable" 
                                            name="middle_name" class="@error('middle_name') is-invalid @enderror"
                                            value="{{ old('middle_name') }}">
                                        @error('middle_name')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Last name</label>
                                        <input type="text" class="form-control" placeholder="e.g. Dela Cruz" 
                                            name="last_name" class="@error('last_name') is-invalid @enderror"
                                            value="{{ old('last_name') }}">
                                        @error('last_name')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="#">Address: Sitio/Purok</label>
                                        <input type="text" class="form-control" placeholder="e.g. Purok 2" 
                                            name="sitio" class="@error('sitio') is-invalid @enderror"
                                            value="{{ old('sitio') }}">
                                        @error('sitio')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="#">Barangay</label>
                                        <input type="text" class="form-control" placeholder="e.g. Poblacion" 
                                            name="barangay" class="@error('barangay') is-invalid @enderror"
                                            value="{{ old('barangay') }}">
                                        @error('barangay')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Municipality</label>
                                        <select type="text" class="form-control" placeholder="" 
                                            name="municipality" class="@error('municipality') is-invalid @enderror"
                                            value="{{ old('municipality') }}">
                                                <option value="">---please select---</option>
                                            @foreach($towns as $town)
                                                <option value="{{$town->name}}" {{ old('municipality') == $town->name ? "selected":"" }}>{{$town->name}}</option>
                                            @endforeach
                                                <option value="Tagbilaran City" {{ old('municipality') == 'Tagbilaran City' ? "selected":"" }}>Tagbilaran City</option>
                                        </select>
                                        @error('municipality')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="#">Zip code</label>
                                        <input type="number" class="form-control" placeholder="e.g. 6331" 
                                            name="zip" class="@error('zip') is-invalid @enderror"
                                            value="{{ old('zip') }}">
                                        @error('zip')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="#">Age</label>
                                        <input type="number" class="form-control" placeholder="18" 
                                            name="age" class="@error('age') is-invalid @enderror"
                                            value="{{ old('age') }}">
                                        @error('age')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="#">Gender</label>
                                        <select type="text" class="form-control" placeholder="" 
                                            name="gender" class="@error('gender') is-invalid @enderror"
                                            value="{{ old('gender') }}">
                                            <option value="">---please select---</option>
                                            @foreach($sexes as $sex)
                                                <option value="{{$sex->details}}" {{ old('gender') == $sex->details ? "selected":"" }}>{{$sex->details}}</option>
                                            @endforeach
                                        </select>
                                        @error('gender')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Civil status</label>
                                        <select type="text" class="form-control" placeholder="" 
                                            name="civil_status" class="@error('civil_status') is-invalid @enderror"
                                            value="{{ old('civil_status') }}">
                                            <option value="">---please select---</option>
                                            @foreach($civilstatuses as $civil_status)
                                                <option value="{{$civil_status->details}}" {{ old('civil_status') == $civil_status->details ? "selected":"" }}>{{$civil_status->details}}</option>
                                            @endforeach
                                        </select>
                                        @error('civil_status')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Religion</label>
                                        <input type="text" class="form-control" placeholder="e.g. Christianity or '-' if not applicable" 
                                            name="religion" class="@error('religion') is-invalid @enderror"
                                            value="{{ old('religion') }}">
                                        @error('religion')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                
                            </div>
                            <div class="row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="#">Disability</label>
                                        <input type="text" class="form-control" placeholder="e.g. Blind or '-' if not applicable" 
                                            name="disability" class="@error('disability') is-invalid @enderror"
                                            value="{{ old('disability') }}">
                                        @error('disability')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="#">Ethnic group</label>
                                        <input type="text" class="form-control" placeholder="e.g. Eskaya or '-' if not applicable" 
                                            name="ethnic_group" class="@error('ethnic_group') is-invalid @enderror"
                                            value="{{ old('ethnic_group') }}">
                                        @error('ethnic_group')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="#">Email address</label>
                                        <input type="email" class="form-control" placeholder="e.g. username@email.com" 
                                            name="email" class="@error('email') is-invalid @enderror"
                                            value="{{ old('email') }}">
                                        @error('email')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="#">Phone number</label>
                                        <input type="text" class="form-control" placeholder="e.g. 09205001182" 
                                            name="phone" class="@error('phone') is-invalid @enderror"
                                            value="{{ old('phone') }}">
                                        @error('phone')
                                            <span class="text-danger"><small>{{ $message }}</small></span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Submit application</button>
                            <button type="submit" class="btn btn-default float-right">Cancel</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('js')
    <script> console.log('Hi!'); </script>
    <script>
        $(function () {
            $("#vacancies").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 7,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@endsection

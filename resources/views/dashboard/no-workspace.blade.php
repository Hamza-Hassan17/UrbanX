@extends('layouts.errors.master')

@section('title', 'No Workspace Assigned')

@section('content')
    <div class="misc-wrapper text-center">
        <h1 class="mb-2 mx-2 text-warning" style="line-height: 6rem; font-size: 6rem">🔒</h1>
        <h4 class="mb-2 mx-2">{{__('No Workspace Assigned')}}</h4>
        <p class="mb-6 mx-2">{{__('Your account has not been assigned to a workspace yet. Please contact your administrator.')}}</p>
        <div class="d-flex">
            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-danger mb-10 mx-4">{{__('Logout')}}</a>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
        <div class="mt-4">
            <img src="{{ asset('assets/img/illustrations/page-misc-error.png') }}" alt="No Workspace Assigned" width="225" class="img-fluid" />
        </div>
    </div>
@endsection

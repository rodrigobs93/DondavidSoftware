@extends('layouts.app')
@section('title', 'Nuevo Proveedor')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-4">Nuevo Proveedor</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('suppliers.store') }}">
            @csrf
            @include('suppliers._form', ['supplier' => null])
            <div class="flex gap-2 mt-4">
                <button class="pos-btn-primary">Guardar</button>
                <a href="{{ route('suppliers.index') }}" class="pos-btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

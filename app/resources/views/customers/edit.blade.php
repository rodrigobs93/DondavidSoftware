@extends('layouts.app')
@section('title', 'Editar Cliente')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-4">Editar Cliente</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('customers._form', ['customer' => $customer])
            <div class="mt-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="active" value="1" @checked($customer->active)>
                    Cliente activo
                </label>
            </div>
            <div class="flex gap-2 mt-4">
                <button class="pos-btn-primary">Actualizar</button>
                <a href="{{ route('customers.index') }}" class="pos-btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

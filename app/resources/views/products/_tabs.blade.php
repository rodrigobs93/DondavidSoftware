{{-- Products area tab navigation — $active is 'precios' | 'cotizacion' --}}
<div class="flex items-center justify-between mb-4 flex-wrap gap-2">
    <div class="flex border-b border-gray-200 w-full sm:w-auto">
        <a href="{{ route('products.index') }}"
           class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px min-h-[44px] inline-flex items-center
                  {{ $active === 'precios' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Precios
        </a>
        <a href="{{ route('products.cotizacion') }}"
           class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px min-h-[44px] inline-flex items-center
                  {{ $active === 'cotizacion' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Cotización
        </a>
    </div>
    @if($active === 'precios')
        <a href="{{ route('categories.index') }}" class="text-sm text-blue-600 hover:text-blue-800">Gestionar categorías →</a>
    @endif
</div>

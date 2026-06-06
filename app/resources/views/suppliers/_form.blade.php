<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
        <input type="text" name="name" value="{{ old('name', $supplier?->name) }}"
            data-keyboard="text"
            class="form-input w-full border rounded px-3 py-2 text-sm" required>
        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">NIT / Identificación</label>
            <input type="text" name="tax_id" value="{{ old('tax_id', $supplier?->tax_id) }}"
                data-keyboard="numeric"
                class="w-full border rounded px-3 py-2 text-sm" placeholder="900.123.456-1">
            @error('tax_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
            <input type="text" name="phone" value="{{ old('phone', $supplier?->phone) }}"
                data-keyboard="numeric"
                class="w-full border rounded px-3 py-2 text-sm" placeholder="3001234567">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Contacto</label>
        <input type="text" name="contact" value="{{ old('contact', $supplier?->contact) }}"
            data-keyboard="text"
            class="w-full border rounded px-3 py-2 text-sm" placeholder="Nombre de la persona de contacto">
        @error('contact') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
        <textarea name="notes" rows="2" data-keyboard="text" class="w-full border rounded px-3 py-2 text-sm">{{ old('notes', $supplier?->notes) }}</textarea>
        @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    @if($supplier)
        <div>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="active" value="1" @checked(old('active', $supplier->active))>
                <span class="font-medium text-gray-700">Proveedor activo</span>
            </label>
        </div>
    @endif
</div>

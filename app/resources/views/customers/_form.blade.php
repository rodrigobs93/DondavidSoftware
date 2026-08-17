<div class="space-y-4" x-data="{ docType: '{{ old('doc_type', $customer?->doc_type) }}' }">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
        <input type="text" name="name" value="{{ old('name', $customer?->name) }}"
            data-keyboard="text"
            class="form-input w-full border rounded px-3 py-2 text-sm" required>
        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Nombre restaurante/razón social
            <span x-show="docType === 'NIT'" class="text-red-500">*</span>
        </label>
        <input type="text" name="business_name" value="{{ old('business_name', $customer?->business_name) }}"
            data-keyboard="text"
            class="w-full border rounded px-3 py-2 text-sm"
            placeholder="Restaurante La Leña S.A.S."
            :required="docType === 'NIT'">
        <p class="text-xs text-gray-400 mt-1" x-show="docType === 'NIT'">Requerido para clientes con NIT.</p>
        @error('business_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo doc.</label>
            <select name="doc_type" @change="docType = $event.target.value"
                class="w-full border rounded px-3 py-2 text-sm">
                <option value="">Sin documento</option>
                <option value="NIT" @selected(old('doc_type', $customer?->doc_type) === 'NIT')>NIT</option>
                <option value="CC" @selected(old('doc_type', $customer?->doc_type) === 'CC')>C.C.</option>
            </select>
            @error('doc_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Número doc.</label>
            <input type="text" name="doc_number" value="{{ old('doc_number', $customer?->doc_number) }}"
                data-keyboard="numeric"
                class="w-full border rounded px-3 py-2 text-sm" placeholder="900.123.456-1">
            @error('doc_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
        <input type="text" name="phone" value="{{ old('phone', $customer?->phone) }}"
            data-keyboard="numeric"
            class="w-full border rounded px-3 py-2 text-sm" placeholder="3001234567">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
        <input type="text" name="address" value="{{ old('address', $customer?->address) }}"
            data-keyboard="text"
            class="w-full border rounded px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Valor de domicilio (opcional)</label>
        <div class="flex items-center gap-2">
            <span class="text-gray-500 text-sm">$</span>
            <input type="number" name="default_delivery_fee"
                value="{{ old('default_delivery_fee', $customer?->default_delivery_fee !== null ? (int) $customer->default_delivery_fee : '') }}"
                inputmode="numeric" data-keyboard="numeric"
                min="0" step="500" placeholder="Sin domicilio"
                class="form-input w-full border rounded px-3 py-2 text-sm">
        </div>
        <p class="text-xs text-gray-400 mt-1">Se cargará automáticamente al crear una factura para este cliente. Déjalo vacío si no cobra domicilio.</p>
        @error('default_delivery_fee') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $customer?->email) }}"
            data-keyboard="text"
            class="w-full border rounded px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
        <textarea name="notes" rows="2" data-keyboard="text" class="w-full border rounded px-3 py-2 text-sm">{{ old('notes', $customer?->notes) }}</textarea>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="requires_fe" value="1"
                @checked(old('requires_fe', $customer?->requires_fe))>
            <span class="font-medium text-gray-700">Requiere Factura Electrónica por defecto</span>
        </label>
    </div>
</div>

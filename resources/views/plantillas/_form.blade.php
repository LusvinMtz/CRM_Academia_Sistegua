<div class="card">
    <div class="card-body p-lg-10">
        <div class="row g-6">
            <div class="col-md-9">
                <input type="hidden" name="tipo_evento" value="{{ \App\Models\Evento::CAPACITACION }}">
                <label for="nombre" class="required form-label fw-semibold">Nombre de la plantilla</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $plantilla->nombre) }}" required maxlength="100"
                       class="form-control form-control-solid @error('nombre') is-invalid @enderror" placeholder="Por ejemplo: Invitación a taller práctico">
                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <input type="hidden" name="predeterminada" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                    <input class="form-check-input" type="checkbox" name="predeterminada" value="1" @checked(old('predeterminada', $plantilla->predeterminada))>
                    <span class="form-check-label fw-semibold text-gray-700">Predeterminada</span>
                </label>
            </div>
            <div class="col-12">
                @include('eventos._campos_correo', [
                    'asunto' => old('asunto', $plantilla->asunto),
                    'mensaje' => old('mensaje', $plantilla->mensaje),
                ])
                @error('asunto') <div class="text-danger fs-7 mt-2">{{ $message }}</div> @enderror
                @error('mensaje') <div class="text-danger fs-7 mt-2">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ route('plantillas.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $plantilla->exists ? 'Guardar cambios' : 'Crear plantilla' }}</button>
    </div>
</div>

@push('scripts')
    <script>
        document.querySelectorAll('[data-variable]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var area = chip.closest('form').querySelector('[name="mensaje"]');
                var ini = area.selectionStart, fin = area.selectionEnd;
                area.value = area.value.slice(0, ini) + chip.dataset.variable + area.value.slice(fin);
                area.focus();
                area.selectionStart = area.selectionEnd = ini + chip.dataset.variable.length;
            });
        });
    </script>
@endpush

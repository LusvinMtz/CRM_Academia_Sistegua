@extends('layouts.app')

@section('title', 'Editor de certificado')
@section('content')
    <form method="POST" action="{{ route('certificados.update', $plantilla) }}" id="formDiseno">
        @csrf @method('PUT')
        <input type="hidden" name="elementos" id="elementosJson">
        <input type="hidden" name="fondo" id="fondoInput" value="{{ $plantilla->fondo }}">

        <div class="card mb-5">
            <div class="card-body d-flex flex-wrap align-items-end gap-4 py-5">
                <div class="flex-grow-1" style="min-width:240px">
                    <label for="nombre" class="form-label fw-semibold">Nombre del diseño</label>
                    <input id="nombre" name="nombre" value="{{ old('nombre', $plantilla->nombre) }}" required maxlength="100"
                           class="form-control form-control-solid @error('nombre') is-invalid @enderror">
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div style="min-width:170px">
                    <label for="orientacion" class="form-label fw-semibold">Orientación</label>
                    <select id="orientacion" name="orientacion" class="form-select form-select-solid">
                        <option value="horizontal" @selected($plantilla->orientacion === 'horizontal')>Horizontal</option>
                        <option value="vertical" @selected($plantilla->orientacion === 'vertical')>Vertical</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('certificados.vista-previa', $plantilla) }}" target="_blank" class="btn btn-light">Vista previa PDF</a>
                    <a href="{{ route('certificados.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar diseño</button>
                </div>
            </div>
        </div>

        <div class="row g-5">
            {{-- Lienzo --}}
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button type="button" class="btn btn-sm btn-light-primary" data-agregar="texto">
                                <i class="ki-outline ki-text fs-4"></i> Texto
                            </button>
                            <button type="button" class="btn btn-sm btn-light-primary" data-agregar="linea">
                                <i class="ki-outline ki-minus fs-4"></i> Línea
                            </button>
                            <button type="button" class="btn btn-sm btn-light-primary" data-agregar="marco">
                                <i class="ki-outline ki-frame fs-4"></i> Marco
                            </button>
                            <button type="button" class="btn btn-sm btn-light-primary" data-agregar="imagen">
                                <i class="ki-outline ki-picture fs-4"></i> Logo
                            </button>
                            <label class="btn btn-sm btn-light mb-0">
                                Subir imagen (firma, sello)
                                <input type="file" id="subirImagen" accept="image/png,image/jpeg" hidden>
                            </label>
                            <label class="btn btn-sm btn-light mb-0">
                                Fondo
                                <input type="file" id="subirFondo" accept="image/png,image/jpeg" hidden>
                            </label>
                            <button type="button" class="btn btn-sm btn-light-danger" id="quitarFondo" @disabled(! $plantilla->fondo)>Quitar fondo</button>
                        </div>

                        <div id="lienzoCaja" class="mx-auto" style="max-width:100%">
                            <div id="lienzo" class="position-relative border border-gray-300 bg-white shadow-sm"
                                 style="width:100%; overflow:hidden; user-select:none; touch-action:none;"></div>
                        </div>
                        <div class="form-text mt-3">
                            Arrastre los elementos para moverlos. Use el punto de la derecha para cambiar el ancho.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel de propiedades --}}
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h4 class="fw-bold text-gray-800 mb-5">Elemento seleccionado</h4>

                        <div id="sinSeleccion" class="text-muted fs-7 mb-5">
                            Haga clic en un elemento del certificado para editarlo.
                        </div>

                        <div id="props" class="d-none">
                            <div class="mb-4" data-solo="texto">
                                <label class="form-label fw-semibold fs-7">Texto</label>
                                <textarea id="pTexto" rows="3" class="form-control form-control-sm form-control-solid"></textarea>
                                <div class="form-text">
                                    Variables:
                                    @foreach (array_keys(\App\Models\PlantillaCertificado::VARIABLES) as $v)
                                        <button type="button" class="btn btn-sm btn-light py-1 px-2 my-1 fs-8" data-variable="{{ $v }}">{{ $v }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="form-label fw-semibold fs-7">Posición X (%)</label>
                                    <input type="number" id="pX" step="0.5" class="form-control form-control-sm form-control-solid">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold fs-7">Posición Y (%)</label>
                                    <input type="number" id="pY" step="0.5" class="form-control form-control-sm form-control-solid">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold fs-7">Ancho (%)</label>
                                    <input type="number" id="pAncho" step="0.5" class="form-control form-control-sm form-control-solid">
                                </div>
                                <div class="col-6" data-solo="marco imagen">
                                    <label class="form-label fw-semibold fs-7">Alto (%)</label>
                                    <input type="number" id="pAlto" step="0.5" class="form-control form-control-sm form-control-solid">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6" data-solo="texto">
                                    <label class="form-label fw-semibold fs-7">Tamaño (pt)</label>
                                    <input type="number" id="pFuente" min="6" max="72" class="form-control form-control-sm form-control-solid">
                                </div>
                                <div class="col-6" data-solo="texto linea marco">
                                    <label class="form-label fw-semibold fs-7">Color</label>
                                    <input type="color" id="pColor" class="form-control form-control-sm form-control-color w-100">
                                </div>
                                <div class="col-6" data-solo="linea marco">
                                    <label class="form-label fw-semibold fs-7">Grosor (pt)</label>
                                    <input type="number" id="pGrosor" min="1" max="20" class="form-control form-control-sm form-control-solid">
                                </div>
                                <div class="col-6" data-solo="imagen">
                                    <label class="form-label fw-semibold fs-7">Imagen</label>
                                    <select id="pSrc" class="form-select form-select-sm form-select-solid">
                                        <option value="logo">Logo de la academia</option>
                                        @foreach ($imagenes as $img)
                                            <option value="{{ $img['archivo'] }}">{{ $img['archivo'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4" data-solo="texto">
                                <label class="form-label fw-semibold fs-7">Alineación</label>
                                <select id="pAlineacion" class="form-select form-select-sm form-select-solid">
                                    <option value="left">Izquierda</option>
                                    <option value="center">Centro</option>
                                    <option value="right">Derecha</option>
                                </select>
                            </div>

                            <div class="d-flex flex-wrap gap-4 mb-5" data-solo="texto">
                                <label class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="pNegrita">
                                    <span class="form-check-label fs-7">Negrita</span>
                                </label>
                                <label class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="pCursiva">
                                    <span class="form-check-label fs-7">Cursiva</span>
                                </label>
                                <label class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" id="pMayusculas">
                                    <span class="form-check-label fs-7">Mayúsculas</span>
                                </label>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-light" id="alFrente">Traer al frente</button>
                                <button type="button" class="btn btn-sm btn-light" id="alFondo">Enviar atrás</button>
                                <button type="button" class="btn btn-sm btn-light" id="duplicarEl">Duplicar</button>
                                <button type="button" class="btn btn-sm btn-light-danger" id="borrarEl">Eliminar</button>
                            </div>
                        </div>

                        <div class="separator my-6"></div>
                        <h4 class="fw-bold text-gray-800 mb-4">Elementos</h4>
                        <div id="listaElementos" class="d-flex flex-column gap-1"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const MEDIDAS = @json(\App\Models\PlantillaCertificado::MEDIDAS);
    const URL_IMAGEN = @json(route('certificados.imagen.ver', 'ARCHIVO'));
    const URL_SUBIR = @json(route('certificados.imagen'));
    const URL_LOGO = @json(asset('assets/media/logos/academia-logo.png'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    let elementos = @json($plantilla->elementos);
    let fondo = @json($plantilla->fondo);
    let seleccionado = null;

    const lienzo = document.getElementById('lienzo');
    const props = document.getElementById('props');
    const sinSeleccion = document.getElementById('sinSeleccion');
    const lista = document.getElementById('listaElementos');
    const orientacion = document.getElementById('orientacion');

    const urlDe = (src) => src === 'logo' ? URL_LOGO : URL_IMAGEN.replace('ARCHIVO', encodeURIComponent(src));
    const medidas = () => MEDIDAS[orientacion.value] || MEDIDAS.horizontal;

    function ajustarLienzo() {
        const m = medidas();
        lienzo.style.aspectRatio = m.ancho + ' / ' + m.alto;
        pintar();
    }

    // Escala entre el lienzo en pantalla y la hoja en puntos, para que el tamaño de letra se vea igual
    const escala = () => lienzo.clientWidth / medidas().ancho;

    function pintar() {
        lienzo.style.backgroundImage = fondo ? `url('${urlDe(fondo)}')` : 'none';
        lienzo.style.backgroundSize = '100% 100%';
        lienzo.innerHTML = '';
        const k = escala();

        elementos.forEach((el, i) => {
            const div = document.createElement('div');
            div.className = 'position-absolute';
            div.dataset.indice = i;
            div.style.left = el.x + '%';
            div.style.top = el.y + '%';
            div.style.width = el.ancho + '%';
            div.style.cursor = 'move';

            if (el.tipo === 'texto') {
                div.textContent = (el.mayusculas ? (el.texto || '').toUpperCase() : (el.texto || '')) || '(texto vacío)';
                div.style.fontSize = (el.fuente * k) + 'px';
                div.style.lineHeight = '1.4';
                div.style.color = el.color;
                div.style.textAlign = el.alineacion;
                div.style.fontWeight = el.negrita ? 'bold' : 'normal';
                div.style.fontStyle = el.cursiva ? 'italic' : 'normal';
                div.style.whiteSpace = 'pre-wrap';
            } else if (el.tipo === 'linea') {
                div.style.height = (el.grosor * k) + 'px';
                div.style.backgroundColor = el.color;
            } else if (el.tipo === 'marco') {
                div.style.height = el.alto + '%';
                div.style.border = (el.grosor * k) + 'px solid ' + el.color;
            } else if (el.tipo === 'imagen') {
                const img = document.createElement('img');
                img.src = urlDe(el.src || 'logo');
                img.style.width = '100%';
                if (el.alto) { img.style.height = '100%'; }
                img.style.display = 'block';
                img.draggable = false;
                if (el.alto) { div.style.height = el.alto + '%'; }
                div.appendChild(img);
            }

            if (i === seleccionado) {
                div.style.outline = '2px solid #D62228';
                div.style.outlineOffset = '2px';
                const tirador = document.createElement('span');
                tirador.dataset.tirador = '1';
                tirador.className = 'position-absolute bg-primary rounded-circle';
                tirador.style.cssText = 'width:12px;height:12px;right:-6px;top:50%;margin-top:-6px;cursor:ew-resize;';
                div.appendChild(tirador);
            }

            lienzo.appendChild(div);
        });

        pintarLista();
    }

    function pintarLista() {
        lista.innerHTML = '';
        elementos.forEach((el, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm text-start ' + (i === seleccionado ? 'btn-light-primary' : 'btn-light');
            const etiqueta = el.tipo === 'texto' ? (el.texto || '(vacío)').slice(0, 34)
                : el.tipo === 'imagen' ? 'Imagen: ' + (el.src === 'logo' ? 'logo' : el.src)
                : el.tipo === 'linea' ? 'Línea' : 'Marco';
            b.textContent = etiqueta;
            b.onclick = () => { seleccionar(i); };
            lista.appendChild(b);
        });
    }

    function seleccionar(i) {
        seleccionado = i;
        const el = elementos[i];
        props.classList.toggle('d-none', !el);
        sinSeleccion.classList.toggle('d-none', !!el);

        if (el) {
            props.querySelectorAll('[data-solo]').forEach(c => {
                c.classList.toggle('d-none', !c.dataset.solo.split(' ').includes(el.tipo));
            });
            valor('pTexto', el.texto ?? '');
            valor('pX', el.x); valor('pY', el.y); valor('pAncho', el.ancho); valor('pAlto', el.alto ?? 0);
            valor('pFuente', el.fuente ?? 13);
            valor('pColor', el.color ?? '#252F4A');
            valor('pGrosor', el.grosor ?? 1);
            valor('pAlineacion', el.alineacion ?? 'center');
            valor('pSrc', el.src ?? 'logo');
            marcar('pNegrita', el.negrita); marcar('pCursiva', el.cursiva); marcar('pMayusculas', el.mayusculas);
        }
        pintar();
    }

    const valor = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
    const marcar = (id, v) => { const e = document.getElementById(id); if (e) e.checked = !!v; };

    function cambiar(campo, valor) {
        if (seleccionado === null) return;
        elementos[seleccionado][campo] = valor;
        pintar();
    }

    // Propiedades -> elemento
    const enlaces = {
        pTexto: ['texto', v => v], pX: ['x', Number], pY: ['y', Number], pAncho: ['ancho', Number],
        pAlto: ['alto', Number], pFuente: ['fuente', Number], pColor: ['color', v => v],
        pGrosor: ['grosor', Number], pAlineacion: ['alineacion', v => v], pSrc: ['src', v => v],
    };
    Object.entries(enlaces).forEach(([id, [campo, conv]]) => {
        const e = document.getElementById(id);
        if (e) e.addEventListener('input', () => cambiar(campo, conv(e.value)));
    });
    ['pNegrita', 'pCursiva', 'pMayusculas'].forEach(id => {
        const campo = id.replace('p', '').toLowerCase();
        document.getElementById(id).addEventListener('change', e => cambiar(campo, e.target.checked));
    });

    document.querySelectorAll('[data-variable]').forEach(b => {
        b.addEventListener('click', () => {
            const t = document.getElementById('pTexto');
            const pos = t.selectionStart ?? t.value.length;
            t.value = t.value.slice(0, pos) + b.dataset.variable + t.value.slice(pos);
            cambiar('texto', t.value);
            t.focus();
        });
    });

    // Agregar elementos
    const nuevos = {
        texto: () => ({ id: 'el' + Date.now(), tipo: 'texto', texto: 'Texto nuevo', x: 30, y: 45, ancho: 40, fuente: 16, color: '#252F4A', alineacion: 'center', negrita: false, cursiva: false, mayusculas: false }),
        linea: () => ({ id: 'el' + Date.now(), tipo: 'linea', x: 30, y: 50, ancho: 40, color: '#252F4A', grosor: 1 }),
        marco: () => ({ id: 'el' + Date.now(), tipo: 'marco', x: 5, y: 5, ancho: 90, alto: 90, color: '#D62228', grosor: 2 }),
        imagen: () => ({ id: 'el' + Date.now(), tipo: 'imagen', src: 'logo', x: 35, y: 10, ancho: 30, alto: 0 }),
    };
    document.querySelectorAll('[data-agregar]').forEach(b => {
        b.addEventListener('click', () => {
            elementos.push(nuevos[b.dataset.agregar]());
            seleccionar(elementos.length - 1);
        });
    });

    // Acciones sobre el seleccionado
    document.getElementById('borrarEl').addEventListener('click', () => {
        if (seleccionado === null) return;
        elementos.splice(seleccionado, 1);
        seleccionado = null;
        seleccionar(null);
    });
    document.getElementById('duplicarEl').addEventListener('click', () => {
        if (seleccionado === null) return;
        const copia = Object.assign({}, elementos[seleccionado], { id: 'el' + Date.now(), y: elementos[seleccionado].y + 4 });
        elementos.push(copia);
        seleccionar(elementos.length - 1);
    });
    document.getElementById('alFrente').addEventListener('click', () => {
        if (seleccionado === null) return;
        elementos.push(elementos.splice(seleccionado, 1)[0]);
        seleccionar(elementos.length - 1);
    });
    document.getElementById('alFondo').addEventListener('click', () => {
        if (seleccionado === null) return;
        elementos.unshift(elementos.splice(seleccionado, 1)[0]);
        seleccionar(0);
    });

    // Arrastrar y redimensionar
    let arrastre = null;
    lienzo.addEventListener('pointerdown', e => {
        const nodo = e.target.closest('[data-indice]');
        if (!nodo) { seleccionado = null; seleccionar(null); return; }
        const i = Number(nodo.dataset.indice);
        if (i !== seleccionado) seleccionar(i);

        const caja = lienzo.getBoundingClientRect();
        arrastre = {
            modo: e.target.dataset.tirador ? 'ancho' : 'mover',
            x0: e.clientX, y0: e.clientY,
            ex: elementos[i].x, ey: elementos[i].y, ea: elementos[i].ancho,
            caja,
        };
        lienzo.setPointerCapture(e.pointerId);
        e.preventDefault();
    });

    lienzo.addEventListener('pointermove', e => {
        if (!arrastre || seleccionado === null) return;
        const dx = (e.clientX - arrastre.x0) / arrastre.caja.width * 100;
        const dy = (e.clientY - arrastre.y0) / arrastre.caja.height * 100;
        const el = elementos[seleccionado];

        if (arrastre.modo === 'ancho') {
            el.ancho = Math.max(2, Math.round((arrastre.ea + dx) * 2) / 2);
        } else {
            el.x = Math.round((arrastre.ex + dx) * 2) / 2;
            el.y = Math.round((arrastre.ey + dy) * 2) / 2;
        }
        valor('pX', el.x); valor('pY', el.y); valor('pAncho', el.ancho);
        pintar();
    });

    const soltar = e => { if (arrastre) { arrastre = null; lienzo.releasePointerCapture?.(e.pointerId); } };
    lienzo.addEventListener('pointerup', soltar);
    lienzo.addEventListener('pointercancel', soltar);

    // Imágenes
    async function subir(archivo) {
        const datos = new FormData();
        datos.append('imagen', archivo);
        const r = await fetch(URL_SUBIR, { method: 'POST', body: datos, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
        if (!r.ok) { alert('No se pudo subir la imagen.'); return null; }
        return (await r.json()).archivo;
    }

    document.getElementById('subirImagen').addEventListener('change', async e => {
        const archivo = e.target.files[0];
        if (!archivo) return;
        const nombre = await subir(archivo);
        if (!nombre) return;
        const select = document.getElementById('pSrc');
        select.insertAdjacentHTML('beforeend', `<option value="${nombre}">${nombre}</option>`);
        elementos.push({ id: 'el' + Date.now(), tipo: 'imagen', src: nombre, x: 20, y: 70, ancho: 18, alto: 0 });
        seleccionar(elementos.length - 1);
        e.target.value = '';
    });

    document.getElementById('subirFondo').addEventListener('change', async e => {
        const archivo = e.target.files[0];
        if (!archivo) return;
        const nombre = await subir(archivo);
        if (!nombre) return;
        fondo = nombre;
        document.getElementById('fondoInput').value = nombre;
        document.getElementById('quitarFondo').disabled = false;
        pintar();
        e.target.value = '';
    });

    document.getElementById('quitarFondo').addEventListener('click', () => {
        fondo = null;
        document.getElementById('fondoInput').value = '';
        document.getElementById('quitarFondo').disabled = true;
        pintar();
    });

    orientacion.addEventListener('change', ajustarLienzo);
    window.addEventListener('resize', pintar);

    document.getElementById('formDiseno').addEventListener('submit', () => {
        document.getElementById('elementosJson').value = JSON.stringify(elementos);
    });

    ajustarLienzo();
})();
</script>
@endpush

<ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold mb-6">
    <li class="nav-item">
        <a class="nav-link text-active-primary {{ request()->routeIs('reportes.eventos') ? 'active' : '' }}"
           href="{{ route('reportes.eventos', request()->only(['desde', 'hasta', 'sede'])) }}">Por evento</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-active-primary {{ request()->routeIs('reportes.personas') ? 'active' : '' }}"
           href="{{ route('reportes.personas', request()->only(['desde', 'hasta', 'sede'])) }}">Por persona</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-active-primary {{ request()->routeIs('reportes.historial') ? 'active' : '' }}"
           href="{{ route('reportes.historial') }}">Historial por persona</a>
    </li>
</ul>

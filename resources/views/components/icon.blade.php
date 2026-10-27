@props(['name' => 'grid'])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" aria-hidden="true">
    @switch($name)
        @case('filter')
            <path d="M3 5h18M6 12h12M10 19h4" />
            <circle cx="8" cy="5" r="2" /><circle cx="16" cy="12" r="2" />
        @break
        @case('info')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 16v-4m0-4h.01" />
        @break
        @case('grid')
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
        @break

        @case('chart')
            <rect x="4" y="3" width="16" height="18" />
            <path d="M8 17v-4m4 4V8m4 9v-6" />
        @break

        @case('table')
            <rect x="4" y="3" width="16" height="18" />
            <path d="M4 9h16M10 9v12M4 15h16" />
        @break

        @case('bulb')
            <path d="M9 18h6m-5 3h4M8 14a7 7 0 1 1 8 0c-1 1-1 2-1 2H9s0-1-1-2Z" />
        @break

        @case('file')
            <path d="M14 3H5v18h14V8Zm0 0v6h5M8 13h8m-8 4h8" />
        @break

        @case('upload')
            <path d="M14 3H5v18h14V8Zm0 0v6h5m-7 9v-7m-3 3 3-3 3 3" />
        @break

        @case('user')
            <circle cx="12" cy="12" r="9" />
            <circle cx="12" cy="9" r="3" />
            <path d="M6 19c0-6 12-6 12 0" />
        @break

        @case('settings')
            <path d="m9 3 1 3h4l1-3 3 2-1 3 2 3 3 1v3l-3 1-2 3 1 3-3 1-1-3h-4l-1 3-3-1 1-3-2-3-3-1v-3l3-1 2-3-1-3Z"
                transform="translate(1 -1) scale(.92)" />
            <circle cx="12" cy="12" r="3" />
        @break

        @case('eye')
            <path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6-10-6-10-6Z" />
            <circle cx="12" cy="12" r="3" />
        @break

        @case('shield')
            <path d="m12 2 8 4v7c0 5-8 9-8 9s-8-4-8-9V6Z" />
            <path d="M15 7H9v5h6v5H9" />
        @break

        @case('search')
            <circle cx="10" cy="10" r="6" />
            <path d="m15 15 6 6" />
        @break

        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M7 2v6m10-6v6M3 11h18" />
        @break

        @case('box')
            <path d="M3 4h18v5H3Zm2 5v12h14V9m-10 5h6" />
        @break

        @case('money')
            <rect x="2" y="5" width="20" height="14" />
            <circle cx="12" cy="12" r="4" />
            <path d="M5 8h1m12 8h1" />
        @break

        @case('warning')
            <path d="m12 3 10 18H2Zm0 6v5m0 3v1" />
        @break

        @case('download')
            <path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5" />
        @break

        @case('print')
            <path d="M7 8V3h10v5M7 18H3V9h18v9h-4M7 14h10v7H7Zm10-3h1" />
        @break

        @case('logout')
            <path d="M9 3H3v18h6m0-9h13m-4-4 4 4-4 4" />
        @break

        @case('menu')
            <path d="M3 6h18M3 12h18M3 18h18" />
        @break

        @case('plus')
            <path d="M12 4v16M4 12h16" />
        @break

        @case('arrow')
            <path d="M3 12h18m-6-6 6 6-6 6" />
        @break

        @case('sync')
            <path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4m-4 4a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
        @break

        @default
            <path d="m12 3 2 6 7 3-7 2-2 7-2-7-7-2 7-3Z" />
    @endswitch
</svg>

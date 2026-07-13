<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/png" href="/logo-whitemode.png">
    <title>Hasil Pencarian "{{ $keyword }}" - E-DDC</title>
    <meta name="description" content="Hasil klasifikasi multilabel DDC untuk kata kunci: {{ $keyword }}">

    <!-- Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.4s ease both',
                        'shimmer':    'shimmer 2s infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%':   { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        shimmer: {
                            '0%':   { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition:  '200% 0' },
                        },
                    }
                }
            }
        }
    </script>

    <style>
        /* Progress bar warna sesuai probabilitas */
        .prob-bar { transition: width 1s cubic-bezier(.4,0,.2,1); }
        .card-enter { animation: fadeInUp .35s ease both; }

        /* Badge warna per-label (8 jurusan PNJ) */
        .badge-0 { background:#dbeafe; color:#1d4ed8; }
        .badge-1 { background:#dcfce7; color:#15803d; }
        .badge-2 { background:#fef9c3; color:#a16207; }
        .badge-3 { background:#fce7f3; color:#be185d; }
        .badge-4 { background:#ede9fe; color:#6d28d9; }
        .badge-5 { background:#cffafe; color:#0e7490; }
        .badge-6 { background:#fee2e2; color:#b91c1c; }
        .badge-7 { background:#f1f5f9; color:#475569; }
        .badge-8 { background:#e0f2fe; color:#0369a1; }
        .badge-9 { background:#f0fdf4; color:#16a34a; }
        .badge-10 { background:#faf5ff; color:#7e22ce; }
        .badge-11 { background:#fff7ed; color:#c2410c; }

        .bar-0 { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
        .bar-1 { background: linear-gradient(90deg,#22c55e,#4ade80); }
        .bar-2 { background: linear-gradient(90deg,#f59e0b,#fbbf24); }
        .bar-3 { background: linear-gradient(90deg,#ec4899,#f472b6); }
        .bar-4 { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
        .bar-5 { background: linear-gradient(90deg,#06b6d4,#22d3ee); }
        .bar-6 { background: linear-gradient(90deg,#ef4444,#f87171); }
        .bar-7 { background: linear-gradient(90deg,#64748b,#94a3b8); }
        .bar-8 { background: linear-gradient(90deg,#0ea5e9,#38bdf8); }
        .bar-9 { background: linear-gradient(90deg,#10b981,#34d399); }
        .bar-10 { background: linear-gradient(90deg,#a855f7,#c084fc); }
        .bar-11 { background: linear-gradient(90deg,#f97316,#fb923c); }

        /* Modal overlay */
        #detailModal { transition: opacity .2s ease; }
        #doiModal { transition: opacity .2s ease; }

        /* Toggle switch */
        .toggle-btn { transition: all .2s ease; }
        .toggle-btn.active { background: #1e3c72; color: #fff; box-shadow: 0 2px 8px rgba(30,60,114,.3); }
        .toggle-btn:not(.active) { background: #f1f5f9; color: #64748b; }
        .toggle-btn:not(.active):hover { background: #e2e8f0; }
    </style>
</head>

<body class="bg-[#f4f6f9] font-sans text-slate-800 antialiased" style="zoom: 75%;">

    <!-- ─── Navbar ─────────────────────────────────────────────────── -->
    <nav class="fixed start-0 top-0 z-50 w-full border-b border-white/20 bg-white/85 backdrop-blur-md shadow-sm">
        <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between p-4">
            <a href="/" class="flex items-center space-x-3 transition-transform duration-300 hover:scale-105 cursor-pointer">
                <img src="/logo-whitemode.png" class="h-12 w-auto drop-shadow-md" alt="E-DDC Logo" />
                <span class="self-center whitespace-nowrap text-2xl font-extrabold tracking-tight text-[#1e3c72]">
                    E-DDC<span class="text-blue-500">.</span>
                </span>
            </a>

            @auth
            <!-- Mode Switcher -->
            <div class="flex items-center bg-slate-100 rounded-full p-1 border border-slate-200 shadow-inner select-none">
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'database']) }}" 
                   class="rounded-full px-4 py-1.5 text-xs font-bold transition-all duration-200 {{ $mode === 'database' ? 'bg-white text-[#1e3c72] shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Mode Offline
                </a>
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'realtime']) }}" 
                   class="rounded-full px-4 py-1.5 text-xs font-bold transition-all duration-200 {{ $mode === 'realtime' ? 'bg-white text-[#1e3c72] shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Mode Real-time
                </a>
            </div>
            @endauth

            <div class="flex items-center gap-3">
                @auth
                <a href="{{ route('buku.tambah') }}" class="block rounded-full border border-emerald-500 bg-white px-5 py-2.5 text-center text-sm font-semibold text-emerald-600 shadow-sm transition-all hover:-translate-y-0.5 hover:bg-emerald-50">
                    <svg class="inline-block h-4 w-4 mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Buku
                </a>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="block rounded-full bg-red-500 px-6 py-2.5 text-center text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:bg-red-600 cursor-pointer">
                        Logout
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="block rounded-full bg-[#1e3c72] px-6 py-2.5 text-center text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:bg-blue-700">
                    Login
                </a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="h-20"></div><!-- Spacer -->

    <!-- ─── Layout Utama ───────────────────────────────────────────── -->
    <div class="mx-auto mt-6 flex max-w-[1280px] items-start gap-6 px-5 pb-16">

        <!-- ── SIDEBAR ─────────────────────────────────────────────── -->
        <aside class="sticky top-[88px] flex w-[300px] shrink-0 flex-col rounded-2xl border border-slate-200 bg-white shadow-sm h-fit">

            <!-- Filter Header -->
            <div class="rounded-t-2xl bg-gradient-to-r from-[#1e3c72] to-blue-600 px-5 py-4">
                <h2 class="text-sm font-bold text-white/90 uppercase tracking-wider">Filter & Navigasi</h2>
            </div>

             <!-- Sidebar Form -->
            <form id="sideSearchForm" action="{{ route('klasifikasi.process') }}" method="GET" class="flex flex-col overflow-hidden">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="filter_mode" value="and">
                
                <!-- Pencarian -->
                <div class="border-b border-slate-100 p-5">
                    <label class="mb-2.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Pencarian</label>
                    <div class="relative mb-3">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="sideKeyword" name="keyword" value="{{ $keyword }}"
                               placeholder="Judul buku atau No. DDC (cth: 123)" autocomplete="off"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-[13px] text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-[#1e3c72] to-blue-600 py-2.5 text-[12.5px] font-bold text-white shadow-md shadow-blue-900/20 transition hover:from-blue-800 hover:to-blue-700 active:scale-[0.98]">
                        Cari Buku
                    </button>

                    <!-- Switcher Button Filter -->
                    <div class="mt-4 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 border border-slate-200">
                        <button type="button" onclick="switchFilterTab('ai')" id="tabBtnAi"
                                class="rounded-lg py-2 text-center text-[10.5px] font-extrabold tracking-wide uppercase transition-all duration-200 bg-white text-[#1e3c72] shadow-sm cursor-pointer select-none">
                            Klasifikasi AI
                        </button>
                        <button type="button" onclick="switchFilterTab('ddc')" id="tabBtnDdc"
                                class="rounded-lg py-2 text-center text-[10.5px] font-extrabold tracking-wide uppercase transition-all duration-200 text-slate-500 hover:text-slate-700 cursor-pointer select-none">
                            Kelas DDC
                        </button>
                    </div>
            </div>

            <!-- Jurusan PNJ (Static Checkbox List) -->
            <div id="aiFilterSection" class="border-b border-slate-100 p-5 transition-all duration-300">
                <label class="mb-2.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Filter Klasifikasi AI</label>
                @php
                    $pnjClasses = [
                        'Teknik Informatika & Komputer',
                        'Teknik Sipil',
                        'Teknik Mesin',
                        'Teknik Elektro',
                        'Teknik Grafika & Penerbitan',
                        'Administrasi Niaga',
                        'Akuntansi',
                        'Matematika',
                        'Sains',
                        'Novel & Sastra',
                        'Psikologi',
                        'Umum',
                    ];
                    $activeFilters = (array) request('filters', []);
                @endphp
                
                <div class="space-y-1 max-h-[280px] overflow-y-auto pr-1 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300">
                    @foreach($pnjClasses as $nama)
                    <label class="flex w-full cursor-pointer items-start gap-3 rounded-lg px-3 py-2 hover:bg-blue-50/80 transition group">
                        <input type="checkbox" name="filters[]" value="{{ $nama }}" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#1e3c72] focus:ring-[#1e3c72] transition" {{ in_array($nama, $activeFilters) ? 'checked' : '' }}>
                        <span class="text-[12px] leading-tight text-slate-600 group-hover:font-medium group-hover:text-[#1e3c72]">{{ $nama }}</span>
                    </label>
                    @endforeach
                </div>

                <button type="button" onclick="applyAiFilter()" class="mt-3.5 w-full rounded-lg bg-[#1e3c72] py-2 text-center text-[12.5px] font-bold text-white shadow-sm transition hover:bg-blue-900 active:scale-95">
                    Terapkan Filter
                </button>
            </div>
            
            <div id="ddcCategorySection" class="p-5 pb-6 flex flex-col transition-all duration-300" style="display: none;">
                <label class="mb-2.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Kategori DDC</label>
                @php
                    $is300Active = preg_match('/^3\d0-3\d9$/', $keyword);
                @endphp

                <div class="mt-2 space-y-1.5 h-[420px] overflow-y-auto pr-1 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300">
                    
                    <!-- 000 Karya Umum -->
                    <button type="button" onclick="applyDdcCategory('000-069')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '000-069' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '000-069' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            000
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '000-069' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Karya Umum
                        </span>
                    </button>

                    <!-- 070 Jurnalisme dan Media Massa -->
                    <button type="button" onclick="applyDdcCategory('070-079')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '070-079' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '070-079' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            070
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '070-079' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Jurnalisme dan Media Massa
                        </span>
                    </button>

                    <!-- 100 Filsafat Dan Psikologi -->
                    <button type="button" onclick="applyDdcCategory('100-180')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '100-180' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '100-180' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            100
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '100-180' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Filsafat Dan Psikologi
                        </span>
                    </button>

                    <!-- 200 Agama -->
                    <button type="button" onclick="applyDdcCategory('200-299')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '200-299' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '200-299' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            200
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '200-299' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Agama
                        </span>
                    </button>

                    <!-- 300 Ilmu Sosial (Collapsible) -->
                    <div class="flex flex-col gap-1 border border-slate-100 rounded-lg p-1 bg-slate-50/50">
                        <button type="button" onclick="toggleSidebarDdc300(event)"
                                class="group flex w-full items-center justify-between rounded-md px-2 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $is300Active ? 'bg-blue-50/70 border-blue-200' : 'bg-white' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-[28px] w-[28px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $is300Active ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                                    300
                                </span>
                                <span class="text-[12px] leading-snug transition {{ $is300Active ? 'font-bold text-[#1e3c72]' : 'font-semibold text-slate-600 group-hover:text-[#1e3c72]' }}">
                                    Ilmu Sosial
                                </span>
                            </div>
                            <svg id="sidebarChevron300" class="h-4 w-4 text-slate-400 transform transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <!-- Sub-items panel -->
                        <div id="sidebarDdc300Content" class="hidden pl-2 pr-1 py-1 flex flex-col gap-1 border-l border-slate-200 transition-all duration-300">
                            @php
                                $subDdc300 = [
                                    ['300-309', '300', 'Sosiologi & Antropologi'],
                                    ['310-319', '310', 'Statistik Umum'],
                                    ['320-329', '320', 'Ilmu Politik & Pem.'],
                                    ['330-339', '330', 'Ilmu Ekonomi'],
                                    ['340-349', '340', 'Ilmu Hukum'],
                                    ['350-359', '350', 'Adm. Negara & Militer'],
                                    ['360-369', '360', 'Kesejahteraan Sosial'],
                                    ['370-379', '370', 'Pendidikan'],
                                    ['380-389', '380', 'Perdagangan & Trans.'],
                                    ['390-399', '390', 'Adat, Etiket, Folklor'],
                                ];
                            @endphp
                            @foreach($subDdc300 as [$kode, $tampil, $nama])
                            <button type="button" onclick="applyDdcCategory('{{ $kode }}')"
                                    class="group flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left transition hover:bg-blue-50 {{ $keyword === $kode ? 'bg-blue-50 ring-1 ring-blue-100/70' : '' }}">
                                <span class="text-[11px] truncate leading-tight transition {{ $keyword === $kode ? 'font-bold text-[#1e3c72]' : 'text-slate-500 group-hover:text-[#1e3c72]' }}" title="{{ $nama }}">
                                    {{ $nama }}
                                </span>
                                <span class="text-[9px] font-extrabold bg-slate-100 text-slate-400 px-1 py-0.5 rounded group-hover:bg-[#1e3c72] group-hover:text-white transition-colors {{ $keyword === $kode ? 'bg-[#1e3c72] text-white' : '' }}">
                                    {{ $tampil }}
                                </span>
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- 400 Bahasa -->
                    <button type="button" onclick="applyDdcCategory('400-499')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '400-499' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '400-499' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            400
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '400-499' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Bahasa
                        </span>
                    </button>

                    <!-- 500 Matematika, Sains -->
                    <button type="button" onclick="applyDdcCategory('500-599')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '500-599' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '500-599' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            500
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '500-599' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Matematika, Sains
                        </span>
                    </button>

                    <!-- 600 Ilmu teknik Dan Teknologi -->
                    <button type="button" onclick="applyDdcCategory('600-620')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '600-620' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '600-620' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            600
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '600-620' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Ilmu teknik Dan Teknologi
                        </span>
                    </button>

                    <!-- 621 Ilmu Terapan -->
                    <button type="button" onclick="applyDdcCategory('621')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '621' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '621' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            621
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '621' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Ilmu Terapan
                        </span>
                    </button>

                    <!-- 621 Ilmu teknik dan Sipil -->
                    <button type="button" onclick="applyDdcCategory('621-624')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '621-624' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '621-624' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            621
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '621-624' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Ilmu teknik dan Sipil
                        </span>
                    </button>

                    <!-- 650 Akuntansi -->
                    <button type="button" onclick="applyDdcCategory('650-657')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '650-657' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '650-657' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            650
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '650-657' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Akuntansi
                        </span>
                    </button>

                    <!-- 658 Manajemen -->
                    <button type="button" onclick="applyDdcCategory('658')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '658' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '658' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            658
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '658' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Manajemen
                        </span>
                    </button>

                    <!-- 670 Manufaktur -->
                    <button type="button" onclick="applyDdcCategory('670-689')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '670-689' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '670-689' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            670
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '670-689' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Manufaktur
                        </span>
                    </button>

                    <!-- 690 Teknik bangunan -->
                    <button type="button" onclick="applyDdcCategory('691-699')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '691-699' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '691-699' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            690
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '691-699' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Teknik bangunan
                        </span>
                    </button>

                    <!-- 700 Kesenian, Hiburan dan Olahraga -->
                    <button type="button" onclick="applyDdcCategory('790-799')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '790-799' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '790-799' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            700
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '790-799' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Kesenian, Hiburan dan Olahraga
                        </span>
                    </button>

                    <!-- 800 Kesastraan, Retorika, Fiksi dan Non-Fiksi -->
                    <button type="button" onclick="applyDdcCategory('800-899')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '800-899' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '800-899' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            800
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '800-899' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Kesastraan, Retorika, Fiksi dan Non-Fiksi
                        </span>
                    </button>

                    <!-- 900 Geografi Dan Sejarah -->
                    <button type="button" onclick="applyDdcCategory('900-999')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 text-left {{ $keyword === '900-999' ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === '900-999' ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            900
                        </span>
                        <span class="text-[12px] leading-snug transition {{ $keyword === '900-999' ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            Geografi Dan Sejarah
                        </span>
                    </button>

                </div>
            </div>
        </form>
        </aside>

        <script>
            function applyAiFilter() {
                // Submit form beserta keyword yang ada dan filter yang dipilih
                document.getElementById('sideSearchForm').submit();
            }

            function applyDdcCategory(kode) {
                document.getElementById('sideKeyword').value = kode;
                document.querySelectorAll('input[name="filters[]"]').forEach(cb => cb.checked = false);
                document.getElementById('sideSearchForm').submit();
            }

            function removeFilter(filterName) {
                const checkboxes = document.querySelectorAll('input[name="filters[]"]');
                checkboxes.forEach(cb => {
                    if (cb.value === filterName) {
                        cb.checked = false;
                    }
                });
                applyAiFilter();
            }
        </script>

        <!-- ── KONTEN UTAMA ─────────────────────────────────────────── -->
        <main class="flex-1 min-w-0">

            @if(session('success'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 animate-[fadeInUp_0.3s_ease_both]">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700 animate-[fadeInUp_0.3s_ease_both]">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    {{ session('error') }}
                </div>
            </div>
            @endif

            <!-- Info Bar -->
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border-l-4 border-l-[#1e3c72] bg-white px-6 py-4 shadow-sm">
                <div>
                    @if($apiError)
                        <span class="text-sm text-red-500 font-semibold">⚠ Server AI tidak aktif. Jalankan <code class="bg-red-50 px-1 rounded">python api.py</code> terlebih dahulu.</span>
                    @elseif(!empty($keyword) || !empty($filters))
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm text-slate-600">
                                    Ditemukan <b class="text-[#1e3c72]">{{ $pagination['total'] ?? count($books) }}</b> hasil
                                    @if(isset($pagination) && $pagination['total_pages'] > 1)
                                        <span class="text-slate-400 text-xs ml-1">(Hal. {{ $pagination['page'] }}/{{ $pagination['total_pages'] }})</span>
                                    @endif
                                </span>

                            </div>
                            @if(!empty($filters))
                                <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                    @foreach($filters as $index => $f)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-[#1e3c72] border border-blue-100/70">
                                            <svg class="h-2.5 w-2.5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                            {{ $f }}
                                            <button type="button" onclick="removeFilter('{{ $f }}')" class="hover:text-red-500 transition-colors focus:outline-none ml-0.5" title="Hapus filter ini">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                        @if($index < count($filters) - 1)
                                            <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded {{ ($filterMode ?? 'and') === 'and' ? 'bg-blue-100 text-blue-600' : 'bg-emerald-100 text-emerald-600' }} uppercase">
                                                {{ $filterMode ?? 'and' }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @elseif(isset($pagination))
                        <span class="text-sm text-slate-600">
                            Menampilkan <b class="text-[#1e3c72]">{{ count($books) }}</b> dari total <b class="text-[#1e3c72]">{{ $pagination['total'] }}</b> buku
                            @if(isset($pagination) && $pagination['total_pages'] > 1)
                                <span class="text-slate-400 text-xs ml-1">(Hal. {{ $pagination['page'] }}/{{ $pagination['total_pages'] }})</span>
                            @endif
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <!-- Toggle Multilabel View -->
                    <div class="flex items-center rounded-lg border border-slate-200 overflow-hidden">
                        <button onclick="setMultilabelMode('badges')" id="btnBadges" class="toggle-btn active px-3 py-1.5 text-[11px] font-bold" title="Label saja">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </button>
                        <button onclick="setMultilabelMode('bars')" id="btnBars" class="toggle-btn px-3 py-1.5 text-[11px] font-bold" title="Label + Persentase">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </button>
                    </div>

                    <div class="h-5 w-px bg-slate-200"></div>

                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Urutkan:</span>
                        <select id="sortSelect" onchange="sortBooks(this.value)"
                                class="cursor-pointer rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[13px] text-slate-600 outline-none focus:border-blue-400">
                            <option value="default">Paling Relevan</option>
                            <option value="title">Judul (A-Z)</option>
                            <option value="prob">Probabilitas Tertinggi</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Daftar Buku -->
            <div id="booksContainer">
            @forelse($books as $i => $buku)
            @php
               $topLabel = !empty($buku['Multilabel']) ? strtolower($buku['Multilabel'][0]['label']) : '';
            @endphp
            <div class="book-card card-enter mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                 style="animation-delay: {{ $i * 0.05 }}s"
                 data-title="{{ strtolower($buku['Book_Title'] ?? '') }}"
                 data-top-prob="{{ $buku['Multilabel'][0]['probabilitas'] ?? 0 }}"
                 data-top-label="{{ $topLabel }}">

                <div class="flex p-5">

                    <!-- Cover -->
                    <div class="mr-5 h-[150px] w-[100px] shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 shadow-sm">
                        @if(!empty($buku['Image']))
                            <img src="{{ $buku['Image'] }}" alt="Cover" class="h-full w-full object-cover" onerror="this.onerror=null; this.src='/cover.jpeg';">
                        @else
                            <img src="/cover.jpeg" alt="Cover" class="h-full w-full object-cover">
                        @endif
                    </div>

                    <!-- Informasi Buku -->
                    <div class="flex-1 min-w-0">
                        <h2 class="mb-1 text-base font-bold leading-snug text-slate-800 line-clamp-2">
                            {{ $buku['Book_Title'] ?? 'Tanpa Judul' }}
                        </h2>
                        <p class="mb-3 text-[13px] text-slate-500">
                            <span class="font-medium">{{ $buku['Author'] ?? 'Penulis Tidak Diketahui' }}</span>
                            @if(!empty($buku['Year_Published']) && $buku['Year_Published'] !== '-')
                                · <span>{{ $buku['Year_Published'] }}</span>
                            @endif
                            @if(!empty($buku['Publisher']) && $buku['Publisher'] !== '-')
                                · <span>{{ $buku['Publisher'] }}</span>
                            @endif
                        </p>



                        <!-- ── MULTILABEL SECTION ───────────────────── -->
                        @if(!empty($buku['Multilabel']))
                        <div class="mb-3">
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    Klasifikasi AI
                                </p>
                                @if(!empty($buku['has_notes']))
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 ring-1 ring-emerald-200" title="Klasifikasi menggunakan data Notes dari biblio">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    + Notes
                                </span>
                                @endif
                            </div>

                            <!-- Mode: Labels Only (badges) -->
                            <div class="multilabel-badges">
                                <div class="flex flex-wrap gap-1.5">
                                    @php $badgeIdx = 0; @endphp
                                    @foreach($buku['Multilabel'] as $label)
                                        @if($label['probabilitas'] >= 15)
                                        <span class="badge-{{ $badgeIdx }} inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold">
                                            {{ $label['label'] }}
                                        </span>
                                        @php $badgeIdx++; @endphp
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <!-- Mode: With Percentages (progress bars) -->
                            <div class="multilabel-bars hidden">
                                <div class="space-y-1.5">
                                    @php $barIdx = 0; @endphp
                                    @foreach($buku['Multilabel'] as $label)
                                    @if($label['probabilitas'] >= 15)
                                    <div class="flex items-center gap-2">
                                        <span class="w-[170px] shrink-0 truncate text-[11px] text-slate-500">{{ $label['label'] }}</span>
                                        <div class="relative h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                            <div class="bar-{{ $barIdx }} prob-bar h-full rounded-full"
                                                 style="width: {{ $label['probabilitas'] }}%"></div>
                                        </div>
                                        <span class="w-10 shrink-0 text-right text-[11px] font-semibold text-slate-600">
                                            {{ number_format($label['probabilitas'], 1) }}%
                                        </span>
                                    </div>
                                    @php $barIdx++; @endphp
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @else
                        <p class="mb-3 text-[12px] italic text-slate-400">Tidak ada data klasifikasi untuk buku ini.</p>
                        @endif

                    </div>

                    <!-- Kanan: Kode DDC & Tombol -->
                    <div class="ml-4 flex w-[120px] shrink-0 flex-col items-center justify-center border-l border-slate-100 pl-4 text-center">
                        <div class="mb-3 w-full rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">DDC</div>
                            <div class="mt-1 text-xl font-black text-[#1e3c72] leading-tight">
                                {{ isset($buku['DDC_Bersih']) ? sprintf('%03d', $buku['DDC_Bersih']) : ($buku['Book_Code'] ?? '-') }}
                            </div>

                        </div>

                        <button onclick="showDetail({{ $buku['biblio_id'] ?? 0 }}, {{ json_encode($buku) }})"
                                class="w-full rounded-lg border border-[#1e3c72] bg-white py-2 text-[12px] font-semibold text-[#1e3c72] transition hover:bg-[#1e3c72] hover:text-white active:scale-95 mb-1.5">
                            Detail
                        </button>
                        
                        @auth
                        <a href="{{ route('buku.edit', $buku['biblio_id']) }}"
                           class="w-full text-center block rounded-lg border border-amber-500 bg-white py-2 text-[12px] font-semibold text-amber-600 transition hover:bg-amber-500 hover:text-white active:scale-95 mb-1.5">
                            Edit
                        </a>

                        <form action="{{ route('buku.destroy', $buku['biblio_id']) }}" method="POST" class="w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Yakin ingin menghapus buku ini?')"
                                    class="w-full rounded-lg border border-red-500 bg-white py-2 text-[12px] font-semibold text-red-600 transition hover:bg-red-500 hover:text-white active:scale-95">
                                Delete
                            </button>
                        </form>
                        @endauth
                    </div>
                </div>
            </div>

            @empty
            <!-- Empty State -->
            <div class="flex flex-col items-center justify-center rounded-2xl bg-white px-8 py-20 text-center shadow-sm">
                @if($apiError)
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50">
                        <svg class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-red-500">Server AI Tidak Aktif</h3>
                    <p class="max-w-sm text-[14px] text-slate-500">
                        Pastikan server Python sudah berjalan dengan perintah:<br>
                        <code class="mt-2 inline-block rounded bg-slate-100 px-3 py-1 text-[13px] font-mono text-slate-700">python Python_ai/api.py</code>
                    </p>
                @elseif(!empty($filters))
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50">
                        <svg class="h-8 w-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-700">Tidak Ada Buku yang Cocok</h3>
                    <p class="max-w-sm text-[14px] text-slate-500 mb-4">
                        Tidak ada buku yang memiliki <b>semua</b> label berikut secara bersamaan:<br>
                        <span class="mt-1.5 inline-block font-semibold text-[#1e3c72]">{{ implode(' + ', $filters) }}</span>
                    </p>
                    <p class="text-[13px] text-slate-400">
                        Kombinasi ini memang tidak ada di koleksi perpustakaan.<br>
                        Coba pilih hanya 1 prodi, atau kurangi jumlah filter.
                    </p>
                @else
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100">
                        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-700">Buku Tidak Ditemukan</h3>
                    <p class="max-w-sm text-[14px] text-slate-500">
                        Tidak ada koleksi yang cocok dengan <b class="text-slate-700">"{{ $keyword }}"</b>.<br>
                        Coba kata kunci lain atau pilih kategori DDC di sidebar.
                    </p>
                @endif
            </div>

            @endforelse
            </div>

            <!-- ── PAGINATION ── -->
            @if(isset($pagination) && $pagination['total_pages'] > 1)
            <div class="mt-8 flex justify-center items-center gap-2">
                @if($pagination['page'] > 1)
                <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] - 1]) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                    &laquo; Sebelumnya
                </a>
                @endif
                
                <span class="px-4 py-2 rounded-xl bg-slate-100 text-sm font-bold text-slate-700">
                    Halaman {{ $pagination['page'] }} dari {{ $pagination['total_pages'] }}
                </span>

                @if($pagination['page'] < $pagination['total_pages'])
                <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] + 1]) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Selanjutnya &raquo;
                </a>
                @endif
            </div>
            @endif

        </main>
    </div>

    <!-- ─── Modal Detail Buku ─────────────────────────────────────── -->
    <div id="detailModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 px-4 py-8 backdrop-blur-sm">
        <div id="detailCard" class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <!-- Close -->
            <button onclick="closeDetail()" class="absolute right-4 top-4 z-10 rounded-full bg-slate-100 p-2 text-slate-500 transition hover:bg-slate-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div id="detailContent" class="p-8">
                <!-- Diisi oleh JS -->
            </div>
        </div>
    </div>

    <!-- ─── JavaScript ────────────────────────────────────────────── -->
    <script>
        // ── Navigasi DDC dari sidebar ──────────────────────────────
        function searchByDDC(kode) {
            document.getElementById('sideKeyword').value = kode;
            document.getElementById('sideSearchForm').submit();
        }

        // ── Sorting ───────────────────────────────────────────────
        function sortBooks(mode) {
            const container = document.getElementById('booksContainer');
            if (!container) return;
            const cards = [...container.querySelectorAll('.book-card')];

            cards.sort((a, b) => {
                if (mode === 'title') {
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                } else if (mode === 'prob') {
                    return parseFloat(b.dataset.topProb || 0) - parseFloat(a.dataset.topProb || 0);
                }
                return 0;
            });

            cards.forEach(c => container.appendChild(c));
        }

        // ── Modal Detail ──────────────────────────────────────────
        function showDetail(id, buku) {
            const modal   = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');

            // Helper: build multilabel HTML
            function buildMultilabelHtml(multilabel) {
                if (!multilabel || !multilabel.length) return '';
                // Warna per-prodi (12 kelas PNJ) — sinkron dengan badge-0..badge-11 di CSS
                const colors = [
                    '#3b82f6', // Teknik Informatika & Komputer
                    '#22c55e', // Teknik Sipil
                    '#f59e0b', // Teknik Mesin
                    '#ec4899', // Teknik Elektro
                    '#8b5cf6', // Teknik Grafika & Penerbitan
                    '#06b6d4', // Administrasi Niaga
                    '#ef4444', // Akuntansi
                    '#64748b', // Matematika
                    '#0ea5e9', // Sains
                    '#10b981', // Novel & Sastra
                    '#a855f7', // Psikologi
                    '#f97316', // Umum
                ];
                return `
                    <div class="mb-4">
                        <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Klasifikasi Multilabel</p>
                        <div class="space-y-2">
                            ${multilabel.map((l, i) => `
                                <div>
                                    <div class="flex justify-between text-[12px] mb-1">
                                        <span class="font-medium text-slate-700">${l.label}</span>
                                        <span class="font-bold" style="color:${colors[i]||'#64748b'}">${l.probabilitas.toFixed(1)}%</span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-1000"
                                             style="width:${l.probabilitas}%; background:${colors[i]||'#64748b'}"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>`;
            }

            // Helper: build full modal content
            function buildModalContent(buku, multilabelHtml) {
                return `
                    <h2 class="mb-1 pr-8 text-xl font-extrabold leading-snug text-slate-800">${buku.Book_Title || 'Tanpa Judul'}</h2>
                    <p class="mb-5 text-sm text-slate-500">${buku.Author || '-'}</p>

                    <div class="mb-5 grid grid-cols-2 gap-3 text-[13px]">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Tahun Terbit</div>
                            <div class="mt-1 font-semibold text-slate-700">${buku.Year_Published || '-'}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Kode DDC</div>
                            <div class="mt-1 font-semibold text-slate-700">${buku.Book_Code || '-'}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Call Number</div>
                            <div class="mt-1 font-semibold text-slate-700">${buku.Call_Number || '-'}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Halaman</div>
                            <div class="mt-1 font-semibold text-slate-700">${buku.Pages || '-'}</div>
                        </div>

                        <div class="col-span-2 rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Penerbit</div>
                            <div class="mt-1 font-semibold text-slate-700">${buku.Publisher || '-'}</div>
                        </div>
                    </div>

                    ${buku.Description ? `
                    <div class="mb-5 border-t border-slate-100 pt-5">
                        <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <svg class="inline-block h-4 w-4 mr-1 -mt-0.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Deskripsi Buku
                        </p>
                        <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50/50 to-slate-50 p-4">
                            <p class="text-[13px] leading-relaxed text-slate-600">${buku.Description}</p>
                        </div>
                    </div>
                    ` : ''}

                    ${buku.Notes && buku.Notes !== '-' ? `
                    <div class="mb-5 ${buku.Description ? '' : 'border-t border-slate-100 pt-5'}">
                        <div class="flex items-center gap-2 mb-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <svg class="inline-block h-4 w-4 mr-1 -mt-0.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Catatan (Notes)
                            </p>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 ring-1 ring-emerald-200">
                                Digunakan untuk Klasifikasi AI
                            </span>
                        </div>
                        <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/40 to-slate-50 p-4">
                            <p class="text-[13px] leading-relaxed text-slate-600">${buku.Notes}</p>
                        </div>
                    </div>
                    ` : ''}

                    <div id="multilabelSection" class="${(buku.Description || (buku.Notes && buku.Notes !== '-')) ? '' : 'border-t border-slate-100 pt-5'}">
                        ${multilabelHtml}
                    </div>
                `;
            }

            // Tampilkan modal langsung dengan data inline (cepat)
            content.innerHTML = buildModalContent(buku, buildMultilabelHtml(buku.Multilabel));
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => document.getElementById('detailCard').classList.add('scale-100'), 10);

            // Fetch detail lengkap dari API (multilabel real-time untuk 1 buku)
            fetch(`/buku/${id}`)
                .then(r => r.json())
                .then(detail => {
                    if (detail && detail.Multilabel && detail.Multilabel.length > 0) {
                        const section = document.getElementById('multilabelSection');
                        if (section) {
                            section.innerHTML = buildMultilabelHtml(detail.Multilabel);
                        }
                    }
                })
                .catch(() => { /* Tetap tampilkan data inline jika API gagal */ });
        }

        function closeDetail() {
            const modal = document.getElementById('detailModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Tutup modal saat klik background
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetail();
        });

        // ── Multilabel Toggle ────────────────────────────────────
        function setMultilabelMode(mode) {
            const allBadges = document.querySelectorAll('.multilabel-badges');
            const allBars   = document.querySelectorAll('.multilabel-bars');
            const btnBadges = document.getElementById('btnBadges');
            const btnBars   = document.getElementById('btnBars');

            if (mode === 'bars') {
                allBadges.forEach(el => el.classList.add('hidden'));
                allBars.forEach(el => el.classList.remove('hidden'));
                btnBars.classList.add('active');
                btnBadges.classList.remove('active');
            } else {
                allBadges.forEach(el => el.classList.remove('hidden'));
                allBars.forEach(el => el.classList.add('hidden'));
                btnBadges.classList.add('active');
                btnBars.classList.remove('active');
            }
        }

        // ── DOI Modal ─────────────────────────────────────────────
        function showDoi(buku) {
            const modal   = document.getElementById('doiModal');
            const content = document.getElementById('doiContent');

            const hasDoi = buku.DOI && buku.DOI !== '-' && buku.DOI !== '';

            if (!hasDoi) {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-5xl mb-3">🔗</div>
                        <h3 class="text-lg font-bold text-slate-700 mb-2">Tidak Ada DOI</h3>
                        <p class="text-sm text-slate-500 max-w-xs mx-auto">Digital Object Identifier (DOI) tidak tersedia untuk buku ini.</p>
                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left">
                            <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">Info Buku</div>
                            <div class="text-[13px] font-semibold text-slate-700">${buku.Book_Title || 'Tanpa Judul'}</div>
                            <div class="text-[12px] text-slate-500 mt-0.5">${buku.Author || '-'} · ${buku.Year_Published || '-'}</div>
                            ${buku.ISBN && buku.ISBN !== '-' ? `<div class="text-[12px] text-slate-500 mt-0.5">ISBN: ${buku.ISBN}</div>` : ''}
                        </div>
                    </div>
                `;
            } else {
                const doiUrl = buku.DOI.startsWith('http') ? buku.DOI : 'https://doi.org/' + buku.DOI;
                content.innerHTML = `
                    <h3 class="mb-1 text-lg font-extrabold text-slate-800">Digital Object Identifier</h3>
                    <p class="mb-5 text-[13px] text-slate-500">${buku.Book_Title || 'Tanpa Judul'}</p>

                    <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50/50 to-slate-50 p-5">
                        <div class="text-[10px] font-bold uppercase text-slate-400 mb-2">DOI</div>
                        <a href="${doiUrl}" target="_blank" class="text-[14px] font-semibold text-[#1e3c72] hover:underline break-all">${buku.DOI}</a>
                    </div>

                    <a href="${doiUrl}" target="_blank" class="mt-5 block w-full rounded-xl bg-gradient-to-r from-[#1e3c72] to-blue-600 py-2.5 text-center text-[12.5px] font-bold text-white shadow-md transition hover:from-blue-800 hover:to-blue-700 active:scale-[0.98]">
                        🔗 Buka DOI
                    </a>
                `;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDoi() {
            const modal = document.getElementById('doiModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('doiModal').addEventListener('click', function(e) {
            if (e.target === this) closeDoi();
        });

        // ── Tab Switcher Logic ────────────────────────────────────
        function switchFilterTab(tab) {
            const tabAi = document.getElementById('tabBtnAi');
            const tabDdc = document.getElementById('tabBtnDdc');
            const secAi = document.getElementById('aiFilterSection');
            const secDdc = document.getElementById('ddcCategorySection');
            
            if (!tabAi || !tabDdc || !secAi || !secDdc) return;

            if (tab === 'ai') {
                tabAi.classList.add('bg-white', 'text-[#1e3c72]', 'shadow-sm');
                tabAi.classList.remove('text-slate-500', 'hover:text-slate-700');
                tabDdc.classList.remove('bg-white', 'text-[#1e3c72]', 'shadow-sm');
                tabDdc.classList.add('text-slate-500', 'hover:text-slate-700');
                
                secAi.style.display = 'block';
                secDdc.style.display = 'none';
                localStorage.setItem('activeFilterTab', 'ai');
            } else {
                tabDdc.classList.add('bg-white', 'text-[#1e3c72]', 'shadow-sm');
                tabDdc.classList.remove('text-slate-500', 'hover:text-slate-700');
                tabAi.classList.remove('bg-white', 'text-[#1e3c72]', 'shadow-sm');
                tabAi.classList.add('text-slate-500', 'hover:text-slate-700');
                
                secDdc.style.display = 'flex';
                secAi.style.display = 'none';
                localStorage.setItem('activeFilterTab', 'ddc');
            }
        }
        
        function toggleSidebarDdc300(event) {
            event.stopPropagation();
            const content = document.getElementById('sidebarDdc300Content');
            const chevron = document.getElementById('sidebarChevron300');
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }
        
        // Auto-initialize tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const hasFilters = {{ !empty($filters) ? 'true' : 'false' }};
            const keyword = "{{ $keyword }}";
            const isRangeOrDdc = /^\d{3}(-\d{3})?$/.test(keyword);
            
            let savedTab = localStorage.getItem('activeFilterTab');
            if (hasFilters) {
                savedTab = 'ai';
            } else if (isRangeOrDdc) {
                savedTab = 'ddc';
            }
            
            switchFilterTab(savedTab || 'ai');

            // Auto-expand 300 section in sidebar if active search matches any 300 range
            if (/^3\d0-3\d9$/.test(keyword)) {
                const content = document.getElementById('sidebarDdc300Content');
                const chevron = document.getElementById('sidebarChevron300');
                if (content) content.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
            }
        });
    </script>

    <!-- ─── Modal DOI ─────────────────────────────────────────── -->
    <div id="doiModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 px-4 py-8 backdrop-blur-sm">
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <button onclick="closeDoi()" class="absolute right-4 top-4 z-10 rounded-full bg-slate-100 p-2 text-slate-500 transition hover:bg-slate-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div id="doiContent" class="p-8">
                <!-- Diisi oleh JS -->
            </div>
        </div>
    </div>

</body>
</html>
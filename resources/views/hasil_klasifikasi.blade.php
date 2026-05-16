<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .bar-0 { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
        .bar-1 { background: linear-gradient(90deg,#22c55e,#4ade80); }
        .bar-2 { background: linear-gradient(90deg,#f59e0b,#fbbf24); }
        .bar-3 { background: linear-gradient(90deg,#ec4899,#f472b6); }
        .bar-4 { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
        .bar-5 { background: linear-gradient(90deg,#06b6d4,#22d3ee); }
        .bar-6 { background: linear-gradient(90deg,#ef4444,#f87171); }
        .bar-7 { background: linear-gradient(90deg,#64748b,#94a3b8); }

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

            <a href="/" class="block rounded-full bg-[#1e3c72] px-6 py-2.5 text-center text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:bg-blue-700">
                Home
            </a>
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
                
                <!-- Pencarian -->
                <div class="border-b border-slate-100 p-5">
                    <label class="mb-2.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Pencarian</label>
                    <div class="relative mb-3">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="sideKeyword" name="keyword" value="{{ $keyword }}"
                               placeholder="Cari buku, DDC..." autocomplete="off"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-[13px] text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-[#1e3c72] to-blue-600 py-2.5 text-[12.5px] font-bold text-white shadow-md shadow-blue-900/20 transition hover:from-blue-800 hover:to-blue-700 active:scale-[0.98]">
                        Cari Buku
                    </button>
            </div>

            <!-- Jurusan PNJ (Multi-Select Dropdown) -->
            <div class="border-b border-slate-100 p-5">
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
                        'Umum',
                    ];
                    $activeFilters = request('filters', []);
                @endphp
                
                <div class="relative w-full" id="multiSelectDropdown">
                    <button type="button" onclick="toggleDropdown()" class="w-full flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-[13px] text-slate-700 shadow-sm transition hover:border-blue-300 hover:shadow-md focus:border-[#1e3c72] focus:ring-4 focus:ring-blue-50">
                        <span id="dropdownLabel" class="truncate font-medium">Semua Jurusan PNJ</span>
                        <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="dropdownMenu" class="absolute z-20 mt-2 hidden w-full overflow-hidden rounded-xl border border-slate-100 bg-white shadow-2xl ring-1 ring-black/5">
                        <div class="p-2 space-y-1 max-h-72 overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300">
                            @foreach($pnjClasses as $nama)
                            <label class="flex w-full cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 hover:bg-blue-50/80 transition group">
                                <input type="checkbox" name="filters[]" value="{{ $nama }}" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#1e3c72] focus:ring-[#1e3c72] transition" {{ in_array($nama, $activeFilters) ? 'checked' : '' }}>
                                <span class="text-[12.5px] leading-tight text-slate-600 group-hover:font-medium group-hover:text-[#1e3c72]">{{ $nama }}</span>
                            </label>
                            @endforeach
                        </div>
                        <div class="border-t border-slate-100 bg-slate-50/80 p-3">
                            <button type="button" onclick="applyAiFilter()" class="w-full rounded-lg bg-[#1e3c72] py-2 text-center text-[12.5px] font-bold text-white shadow-sm transition hover:bg-blue-900 active:scale-95">
                                Terapkan Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-5 pb-6 flex flex-col">
                <label class="mb-2.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Kategori DDC</label>
                @php
                    $ddcMurni = [
                        ['000-099', 'Teknik Informatika & Komputer'],
                        ['300-399', 'Administrasi Niaga'],
                        ['500-509', 'Sains Umum'],
                        ['510-519', 'Matematika'],
                        ['620-629', 'Teknik Sipil & Mesin'],
                        ['621-621', 'Teknik Elektro'],
                        ['650-659', 'Akuntansi & Manajemen'],
                        ['700-779', 'Teknik Grafika & Penerbitan'],
                        ['100-299', 'Umum (Filsafat, Agama)'],
                        ['400-499', 'Umum (Bahasa)'],
                    ];
                @endphp

                <div class="mt-2 space-y-1.5 h-[226px] overflow-y-auto pr-1 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300">
                    @foreach($ddcMurni as [$kode, $nama])
                    <button type="button" onclick="applyDdcCategory('{{ $kode }}')"
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left border border-slate-100 transition hover:bg-blue-50/80 hover:border-blue-200 {{ $keyword === $kode ? 'bg-blue-50 ring-1 ring-blue-100 border-blue-200' : 'bg-white' }}">
                        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-[10px] font-black text-slate-600 transition duration-300 group-hover:from-[#1e3c72] group-hover:to-blue-500 group-hover:text-white {{ $keyword === $kode ? 'from-[#1e3c72] to-blue-500 !text-white' : '' }}">
                            {{ explode('-', $kode)[0] }}
                        </span>
                        <span class="text-[12.5px] leading-snug transition {{ $keyword === $kode ? 'font-bold text-[#1e3c72]' : 'font-medium text-slate-600 group-hover:text-[#1e3c72]' }}">
                            {{ $nama }}
                        </span>
                    </button>
                    @endforeach
                </div>
            </div>
        </form>
        </aside>

        <script>
            function applyAiFilter() {
                const kw = document.getElementById('sideKeyword');
                if (/^\d{3}-\d{3}$/.test(kw.value) || kw.value === 'LAINNYA') {
                    kw.value = '';
                }
                document.getElementById('sideSearchForm').submit();
            }

            function applyDdcCategory(kode) {
                document.getElementById('sideKeyword').value = kode;
                document.querySelectorAll('input[name="filters[]"]').forEach(cb => cb.checked = false);
                document.getElementById('sideSearchForm').submit();
            }

            function toggleDropdown() {
                document.getElementById('dropdownMenu').classList.toggle('hidden');
            }

            // Menutup dropdown AI jika klik di luar
            document.addEventListener('click', function(event) {
                const aiDd = document.getElementById('multiSelectDropdown');
                if (!aiDd.contains(event.target)) {
                    document.getElementById('dropdownMenu').classList.add('hidden');
                }
            });

            // Update label dropdown
            document.addEventListener('DOMContentLoaded', function() {
                // AI Filter label
                const checkboxes = document.querySelectorAll('input[name="filters[]"]');
                const label = document.getElementById('dropdownLabel');
                function updateLabel() {
                    const checked = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
                    if (checked.length === 0) {
                        label.textContent = "Semua Jurusan PNJ";
                        label.classList.remove('text-[#1e3c72]', 'font-bold');
                    } else if (checked.length === 1) {
                        label.textContent = checked[0];
                        label.classList.add('text-[#1e3c72]', 'font-bold');
                    } else {
                        label.textContent = checked.length + " Jurusan Terpilih";
                        label.classList.add('text-[#1e3c72]', 'font-bold');
                    }
                }
                checkboxes.forEach(c => c.addEventListener('change', updateLabel));
                updateLabel();

                // DDC label – show current DDC category if active
                const currentKw = '{{ $keyword }}';
                const ddcLabel = document.getElementById('ddcDropdownLabel');
                const ddcMap = {!! json_encode(collect($ddcMurni)->mapWithKeys(fn($item) => [$item[0] => $item[1]])) !!};
                if (ddcMap[currentKw]) {
                    ddcLabel.textContent = ddcMap[currentKw];
                    ddcLabel.classList.add('text-[#1e3c72]', 'font-bold');
                }
            });
        </script>

        <!-- ── KONTEN UTAMA ─────────────────────────────────────────── -->
        <main class="flex-1 min-w-0">

            <!-- Info Bar -->
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border-l-4 border-l-[#1e3c72] bg-white px-6 py-4 shadow-sm">
                <div>
                    @if($apiError)
                        <span class="text-sm text-red-500 font-semibold">⚠ Server AI tidak aktif. Jalankan <code class="bg-red-50 px-1 rounded">python api.py</code> terlebih dahulu.</span>
                    @elseif(!empty($keyword) || !empty($filters))
                        <span class="text-sm text-slate-600">
                            Ditemukan <b class="text-[#1e3c72]">{{ count($books) }}</b> hasil pencarian.
                        </span>
                    @elseif(isset($pagination))
                        <span class="text-sm text-slate-600">
                            Menampilkan <b class="text-[#1e3c72]">{{ count($books) }}</b> dari total <b class="text-[#1e3c72]">{{ $pagination['total'] }}</b> buku
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
                            <img src="{{ $buku['Image'] }}" alt="Cover" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full flex-col items-center justify-center gap-1">
                                <svg class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <span class="text-[10px] text-slate-400 font-medium">No Cover</span>
                            </div>
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
                                    Klasifikasi Multilabel
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
                                    @foreach($buku['Multilabel'] as $j => $label)
                                        @if($label['probabilitas'] >= 5)
                                        <span class="badge-{{ $j }} inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold">
                                            {{ $label['label'] }}
                                        </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <!-- Mode: With Percentages (progress bars) -->
                            <div class="multilabel-bars hidden">
                                <div class="space-y-1.5">
                                    @foreach($buku['Multilabel'] as $j => $label)
                                    <div class="flex items-center gap-2">
                                        <span class="w-[170px] shrink-0 truncate text-[11px] text-slate-500">{{ $label['label'] }}</span>
                                        <div class="relative h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                            <div class="bar-{{ $j }} prob-bar h-full rounded-full"
                                                 style="width: {{ $label['probabilitas'] }}%"></div>
                                        </div>
                                        <span class="w-10 shrink-0 text-right text-[11px] font-semibold text-slate-600">
                                            {{ number_format($label['probabilitas'], 1) }}%
                                        </span>
                                    </div>
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
                                {{ $buku['Book_Code'] ?? '-' }}
                            </div>
                            @if(!empty($buku['Call_Number']) && $buku['Call_Number'] !== '-')
                            <div class="mt-1 text-[10px] text-slate-500 leading-tight break-all">{{ $buku['Call_Number'] }}</div>
                            @endif
                        </div>

                        <button onclick="showDetail({{ $buku['biblio_id'] ?? 0 }}, {{ json_encode($buku) }})"
                                class="w-full rounded-lg border border-[#1e3c72] bg-white py-2 text-[12px] font-semibold text-[#1e3c72] transition hover:bg-[#1e3c72] hover:text-white active:scale-95">
                            Detail
                        </button>
                    </div>
                </div>
            </div>

            @empty
            <!-- Empty State -->
            <div class="flex flex-col items-center justify-center rounded-2xl bg-white px-8 py-20 text-center shadow-sm">
                <div class="mb-4 text-7xl">📚</div>
                @if($apiError)
                    <h3 class="mb-2 text-lg font-bold text-red-500">Server AI Tidak Aktif</h3>
                    <p class="max-w-sm text-[14px] text-slate-500">
                        Pastikan server Python sudah berjalan dengan perintah:<br>
                        <code class="mt-2 inline-block rounded bg-slate-100 px-3 py-1 text-[13px] font-mono text-slate-700">python Python_ai/api.py</code>
                    </p>
                @else
                    <h3 class="mb-2 text-lg font-bold text-slate-700">Buku Tidak Ditemukan</h3>
                    <p class="max-w-sm text-[14px] text-slate-500">
                        Tidak ada koleksi yang cocok dengan <b class="text-slate-700">"{{ $keyword }}"</b>.
                        Coba kata kunci lain atau pilih kategori DDC di sidebar.
                    </p>
                @endif
            </div>
            @endforelse

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
            const container = document.querySelector('main');
            const cards = [...container.querySelectorAll('.book-card')];
            const parent = cards[0]?.parentElement;
            if (!parent) return;

            cards.sort((a, b) => {
                if (mode === 'title') {
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                } else if (mode === 'prob') {
                    return parseFloat(b.dataset.topProb || 0) - parseFloat(a.dataset.topProb || 0);
                }
                return 0;
            });

            cards.forEach(c => parent.appendChild(c));
        }

        // ── Modal Detail ──────────────────────────────────────────
        function showDetail(id, buku) {
            const modal   = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');

            // Bangun HTML detail
            let multilabelHtml = '';
            if (buku.Multilabel && buku.Multilabel.length) {
                const colors = ['#3b82f6','#22c55e','#f59e0b','#ec4899','#8b5cf6','#06b6d4','#ef4444','#64748b'];
                multilabelHtml = `
                    <div class="mb-4">
                        <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Klasifikasi Multilabel</p>
                        <div class="space-y-2">
                            ${buku.Multilabel.map((l, i) => `
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

            content.innerHTML = `
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

                <div class="${(buku.Description || (buku.Notes && buku.Notes !== '-')) ? '' : 'border-t border-slate-100 pt-5'}">
                    ${multilabelHtml}
                </div>
            `;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => document.getElementById('detailCard').classList.add('scale-100'), 10);
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
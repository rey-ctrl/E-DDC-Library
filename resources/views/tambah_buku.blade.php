<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Buku - E-DDC | Sistem Klasifikasi Perpustakaan</title>
    <meta name="description" content="Tambah koleksi buku baru ke dalam database E-DDC.">

    <!-- Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.4s ease both',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%':   { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f4f6f9] font-sans text-slate-800 antialiased">

    <!-- ─── Navbar ─────────────────────────────────────────────────── -->
    <nav class="fixed start-0 top-0 z-50 w-full border-b border-white/20 bg-white/85 backdrop-blur-md shadow-sm">
        <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between p-4">
            <a href="/" class="flex items-center space-x-3 transition-transform duration-300 hover:scale-105 cursor-pointer">
                <img src="/logo-whitemode.png" class="h-12 w-auto drop-shadow-md" alt="E-DDC Logo" />
                <span class="self-center whitespace-nowrap text-2xl font-extrabold tracking-tight text-[#1e3c72]">
                    E-DDC<span class="text-blue-500">.</span>
                </span>
            </a>

            <div class="flex items-center gap-3">
                <a href="/" class="block rounded-full border border-[#1e3c72] bg-white px-6 py-2.5 text-center text-sm font-semibold text-[#1e3c72] shadow-sm transition-all hover:-translate-y-0.5 hover:bg-slate-50">
                    Home
                </a>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="block rounded-full bg-red-500 px-6 py-2.5 text-center text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:bg-red-600">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="h-24"></div><!-- Spacer -->

    <!-- ─── Main Content ───────────────────────────────────────────── -->
    <div class="mx-auto max-w-3xl px-5 pb-16">

        <!-- Page Header -->
        <div class="mb-8 animate-[fadeInUp_0.4s_ease_both]">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-800">
                Tambah Buku <span class="text-blue-500">Baru</span>
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Isi data buku di bawah ini. Data akan disimpan ke database dan otomatis diklasifikasikan oleh AI.
            </p>
        </div>

        <!-- Success Message -->
        @if(session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 animate-[fadeInUp_0.3s_ease_both]">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        </div>
        @endif

        <!-- Error Message -->
        @if(session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700 animate-[fadeInUp_0.3s_ease_both]">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                {{ session('error') }}
            </div>
        </div>
        @endif

        <!-- Validation Errors -->
        @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 animate-[fadeInUp_0.3s_ease_both]">
            <div class="flex items-center gap-2 mb-2 font-semibold">
                <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                Mohon perbaiki kesalahan berikut:
            </div>
            <ul class="list-disc list-inside space-y-1 pl-7">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Form Card -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden animate-[fadeInUp_0.5s_ease_both]">
            
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-[#1e3c72] to-blue-600 px-6 py-4">
                <h2 class="text-sm font-bold text-white/90 uppercase tracking-wider flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Formulir Data Buku
                </h2>
            </div>

            <form action="{{ route('buku.store') }}" method="POST" class="p-6 space-y-5">
                @csrf

                <!-- Judul Buku -->
                <div>
                    <label for="title" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Judul Buku <span class="text-red-400">*</span>
                    </label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" required
                           placeholder="Masukkan judul buku..."
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                </div>

                <!-- Pengarang / Author (sor) -->
                <div>
                    <label for="sor" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Pengarang <span class="text-red-400">*</span>
                    </label>
                    <input id="sor" type="text" name="sor" value="{{ old('sor') }}" required
                           placeholder="Nama pengarang..."
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                </div>

                <!-- Row: Tahun Terbit + ISBN -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="publish_year" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Tahun Terbit
                        </label>
                        <input id="publish_year" type="text" name="publish_year" value="{{ old('publish_year') }}"
                               placeholder="Contoh: 2024"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                    <div>
                        <label for="isbn_issn" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            ISBN / ISSN
                        </label>
                        <input id="isbn_issn" type="text" name="isbn_issn" value="{{ old('isbn_issn') }}"
                               placeholder="Contoh: 978-3-16-148410-0"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                </div>

                <!-- Row: Kode Klasifikasi (DDC) + Call Number -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="classification" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Kode Klasifikasi DDC <span class="text-red-400">*</span>
                        </label>
                        <input id="classification" type="text" name="classification" value="{{ old('classification') }}" required
                               placeholder="Contoh: 005.133"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                    <div>
                        <label for="call_number" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Call Number
                        </label>
                        <input id="call_number" type="text" name="call_number" value="{{ old('call_number') }}"
                               placeholder="Contoh: 005.133 ABC a"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                </div>

                <!-- Row: Edition + Collation -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="edition" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Edisi
                        </label>
                        <input id="edition" type="text" name="edition" value="{{ old('edition') }}"
                               placeholder="Contoh: Edisi ke-3"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                    <div>
                        <label for="collation" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Kolasi (Halaman)
                        </label>
                        <input id="collation" type="text" name="collation" value="{{ old('collation') }}"
                               placeholder="Contoh: 350 hlm."
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                    </div>
                </div>

                <!-- Series Title -->
                <div>
                    <label for="series_title" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Judul Seri
                    </label>
                    <input id="series_title" type="text" name="series_title" value="{{ old('series_title') }}"
                           placeholder="Contoh: Seri Pemrograman Modern"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50">
                </div>

                <!-- Deskripsi (spec_detail_info) -->
                <div>
                    <label for="spec_detail_info" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Deskripsi Buku
                    </label>
                    <textarea id="spec_detail_info" name="spec_detail_info" rows="3"
                              placeholder="Deskripsi singkat mengenai isi buku..."
                              class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50 resize-y">{{ old('spec_detail_info') }}</textarea>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Catatan (Notes)
                    </label>
                    <textarea id="notes" name="notes" rows="3"
                              placeholder="Catatan tambahan untuk buku ini (digunakan untuk klasifikasi AI)..."
                              class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-sm text-slate-700 outline-none transition focus:border-[#1e3c72] focus:bg-white focus:ring-4 focus:ring-blue-50 resize-y">{{ old('notes') }}</textarea>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        <svg class="inline-block h-3.5 w-3.5 mr-0.5 -mt-0.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Notes akan digunakan oleh AI untuk meningkatkan akurasi klasifikasi buku.
                    </p>
                </div>

                <!-- Divider -->
                <div class="border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-end gap-3">
                        <a href="/"
                           class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 active:scale-95">
                            Batal
                        </a>
                        <button type="submit"
                                class="rounded-xl bg-gradient-to-r from-[#1e3c72] to-blue-600 px-8 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/20 transition-all hover:-translate-y-0.5 hover:from-blue-800 hover:to-blue-700 active:scale-[0.98]">
                            <svg class="inline-block h-4 w-4 mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Simpan Buku
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white py-8 text-center text-sm font-medium text-slate-500">
        &copy; 2026 E-DDC Library System. Dikembangkan untuk keperluan klasifikasi pustaka.
    </footer>

</body>
</html>

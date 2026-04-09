<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Pencarian - E-DDC</title>
    
    <!-- Import Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Import Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Konfigurasi Font Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased selection:bg-blue-200 selection:text-blue-900">

    <!-- Navbar Glassmorphism (Disamakan dengan Landing Page) -->
    <nav class="fixed start-0 top-0 z-50 w-full border-b border-white/20 bg-white/80 shadow-sm backdrop-blur-md transition-all duration-300">
        <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between p-4">
            <a href="/" class="flex cursor-pointer items-center space-x-3 transition-transform duration-300 hover:scale-105 rtl:space-x-reverse">
                <img src="./logo-whitemode.png" class="h-12 w-auto drop-shadow-md" alt="Logo" />
                <span class="self-center whitespace-nowrap text-2xl font-extrabold tracking-tight text-[#1e3c72]">E-DDC<span class="text-blue-500">.</span></span>
            </a>
            
            <div class="hidden w-full md:block md:w-auto" id="navbar-default">
                <ul class="mt-4 flex flex-col items-center rounded-lg border border-slate-100 bg-slate-50 p-4 font-medium md:mt-0 md:flex-row md:space-x-8 md:border-0 md:bg-transparent md:p-0 rtl:space-x-reverse">
                    <li>
                        <a href="/" class="block rounded-full bg-[#1e3c72] px-8 py-2.5 text-center text-sm font-semibold text-white shadow-lg shadow-blue-900/20 transition-all duration-300 hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-blue-900/40">
                            Home
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Layout Utama -->
    <!-- mt-28 ditambahkan agar konten tidak tertutup navbar -->
    <div class="mx-auto mt-28 flex max-w-[1200px] items-start gap-6 px-5 pb-10">
        
        <!-- SIDEBAR KIRI (Sudah Diperbaiki Sticky-nya) -->
        <!-- KUNCI STICKY: sticky, top-[100px], dan h-fit -->
        <aside class="sticky top-[100px] h-fit flex w-[320px] shrink-0 flex-col overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-lg max-h-[calc(100vh-120px)] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-track]:bg-slate-100 [&::-webkit-scrollbar-thumb]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-slate-300 hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">
            
            <!-- Judul Tab -->
            <div class="flex border-b border-slate-200 bg-slate-50">
                <div class="flex-1 border-b-2 border-[#1e3c72] bg-white py-3 pl-5 text-left text-[14px] font-bold text-[#1e3c72]">Contents</div>
            </div>

            <!-- Area Cari Kata -->
            <div class="border-b border-slate-200 bg-white p-5 pb-4">
                <form id="searchForm" action="{{ route('klasifikasi.process') }}" method="POST">
                    @csrf
                    <label class="mb-2 block text-[13px] font-bold text-slate-700">Ketik kata/judul untuk mencari:</label>
                    <div class="flex gap-2">
                        <input type="text" id="keywordInput" name="keyword" value="{{ request('keyword') }}" placeholder="Contoh: manajemen..." required autocomplete="off" 
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-[13px] outline-none transition-colors focus:border-[#3498db] focus:ring-2 focus:ring-[#3498db]/20">
                        <button type="submit" class="rounded-lg bg-gradient-to-r from-[#f39c12] to-orange-500 px-4 py-2 text-[13px] font-bold text-white shadow-md transition-all hover:-translate-y-0.5 hover:shadow-lg">Display</button>
                    </div>
                </form>
            </div>

            <!-- Area Cari Angka DDC -->
            <div class="max-h-[250px] overflow-y-auto bg-white">
                <table class="w-full border-collapse text-[13px]">
                    <thead class="sticky top-0 bg-slate-50/90 backdrop-blur-sm z-10">
                        <tr>
                            <th class="border-b border-slate-200 px-5 py-3 text-left font-bold text-slate-700">Pilih Topik DDC (Angka)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr onclick="searchDDC('650')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">650 - 659 Manajemen</td></tr>
                        <tr onclick="searchDDC('640')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">640 - 649 Kesejahteraan</td></tr>
                        <tr onclick="searchDDC('630')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">630 - 639 Pertanian</td></tr>
                        <tr onclick="searchDDC('670')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">670 - 679 Pabrik</td></tr>
                        <tr onclick="searchDDC('330')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">330 - 339 Ilmu Ekonomi</td></tr>
                        <tr onclick="searchDDC('790')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">790 - 799 Olah Raga</td></tr>
                        <tr onclick="searchDDC('660')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">660 - 669 Teknologi Kimia</td></tr>
                        <tr onclick="searchDDC('000')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">000 - 009 Ilmu Umum</td></tr>
                        <tr onclick="searchDDC('100')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">100 - 199 Filsafat</td></tr>
                        <tr onclick="searchDDC('200')" class="cursor-pointer transition-colors hover:bg-blue-50"><td class="border-b border-slate-100 px-5 py-2.5 font-medium text-slate-600 hover:text-[#1e3c72]">200 - 299 Agama</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Sidebar Filters -->
            <div class="border-t border-slate-200 bg-slate-50 p-5">
                <label class="mb-3 flex cursor-pointer items-center gap-2 text-[13px] font-medium text-slate-600 transition-colors hover:text-[#1e3c72]">
                    <input type="checkbox" class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-[#1e3c72]"> Search previous results
                </label>
                <label class="mb-3 flex cursor-pointer items-center gap-2 text-[13px] font-medium text-slate-600 transition-colors hover:text-[#1e3c72]">
                    <input type="checkbox" checked class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-[#1e3c72]"> Match similar words
                </label>
                <label class="mb-1 flex cursor-pointer items-center gap-2 text-[13px] font-medium text-slate-600 transition-colors hover:text-[#1e3c72]">
                    <input type="checkbox" class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-[#1e3c72]"> Search titles only
                </label>
            </div>
        </aside>

        <!-- KONTEN KANAN -->
        <main class="flex-1">
            
            <!-- Info Bar -->
            <div class="mb-6 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm relative overflow-hidden">
                <div class="absolute left-0 top-0 h-full w-1.5 bg-gradient-to-b from-[#1e3c72] to-[#3498db]"></div>
                <span class="pl-2">Ditemukan <b class="text-[#1e3c72] text-base">{{ count($books) }}</b> hasil untuk: <b class="text-[#1e3c72] bg-blue-50 px-2 py-1 rounded">"{{ request('keyword') }}"</b></span>
                <select class="cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 outline-none transition-colors hover:border-[#3498db] focus:border-[#3498db] focus:ring-2 focus:ring-[#3498db]/20">
                    <option>Paling Relevan</option>
                    <option>Terbaru</option>
                </select>
            </div>

            <!-- Daftar Buku -->
            @forelse($books as $buku)
            <div class="group mb-5 flex rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-xl">
                
                <!-- Cover Dummy -->
                <div class="mr-6 h-[170px] w-[115px] shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-inner">
                    <img src="https://via.placeholder.com/115x170/e2e8f0/94a3b8?text=No+Cover" alt="Cover" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                </div>

                <!-- Informasi Buku -->
                <div class="flex-1">
                    <h2 class="mb-2 text-xl font-bold text-slate-800 transition-colors group-hover:text-[#1e3c72]">{{ $buku['Book_Title'] ?? 'Tanpa Judul' }}</h2>
                    <div class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        {{ $buku['Author'] ?? 'Penulis Tidak Diketahui' }}
                    </div>
                    
                    <div class="mb-3 grid grid-cols-2 gap-y-2 text-[13px] text-slate-500">
                        <div class="flex items-center gap-2">📅 Tahun: <b class="text-slate-700">{{ $buku['Year_Published'] ?? '-' }}</b></div>
                        <div class="flex items-center gap-2">📑 Halaman: <b class="text-slate-700">{{ $buku['Pages'] ?? '-' }}</b></div>
                        <div class="flex items-center gap-2 col-span-2">🏢 Alamat: <b class="text-slate-700">{{ $buku['Alamat'] ?? '-' }}</b></div>
                    </div>

                    <div class="mt-3 border-t border-dashed border-slate-200 pt-3 text-[13px] leading-relaxed text-slate-500">
                        Koleksi tersedia untuk sirkulasi. ID Peminjaman terakhir: <b class="text-slate-700">{{ $buku['ID_Peminjaman'] ?? '-' }}</b>.
                    </div>
                </div>

                <!-- Kode DDC / Aksi -->
                <div class="ml-6 flex w-[140px] shrink-0 flex-col justify-center border-l border-slate-100 pl-6 text-center">
                    <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50/50 p-3 transition-colors group-hover:bg-blue-50">
                        <div class="text-[10px] font-bold tracking-wider text-blue-600/70 uppercase">Kode DDC</div>
                        <div class="mt-1 text-3xl font-black text-[#1e3c72]">{{ $buku['Book_Code'] ?? '000' }}</div>
                    </div>
                    <button class="mb-2 block w-full rounded-lg border-2 border-[#1e3c72] bg-transparent py-2 text-xs font-bold text-[#1e3c72] transition-all hover:bg-[#1e3c72] hover:text-white hover:shadow-md">Detail Buku</button>
                    <button class="block w-full rounded-lg border-2 border-slate-200 bg-transparent py-2 text-xs font-bold text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50">Sitasi MARC</button>
                </div>
            </div>
            
            @empty
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-20 text-center shadow-sm">
                <div class="mb-4 rounded-full bg-red-50 p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <h3 class="mb-2 text-xl font-bold text-slate-800">Buku Tidak Ditemukan</h3>
                <p class="text-[14px] text-slate-500 max-w-md">Maaf, tidak ada klasifikasi atau buku yang cocok dengan kata kunci <b class="text-slate-800">"{{ request('keyword') }}"</b>.</p>
            </div>
            @endforelse

            <!-- Pagination Bar -->
            @if(count($books) > 0)
            <div class="mb-5 mt-10 flex items-center justify-center gap-2">
                <a href="#" class="pointer-events-none rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-[13px] font-bold text-slate-400 shadow-sm no-underline">« Prev</a>
                <a href="#" class="rounded-lg bg-gradient-to-r from-[#1e3c72] to-[#2a5298] px-4 py-2 text-[13px] font-bold text-white shadow-md no-underline">1</a>
                <a href="#" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-[13px] font-bold text-slate-600 shadow-sm no-underline transition-all hover:-translate-y-0.5 hover:border-[#3498db] hover:text-[#1e3c72]">2</a>
                <a href="#" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-[13px] font-bold text-slate-600 shadow-sm no-underline transition-all hover:-translate-y-0.5 hover:border-[#3498db] hover:text-[#1e3c72]">3</a>
                <span class="pointer-events-none rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-[13px] font-bold text-slate-400">...</span>
                <a href="#" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-[13px] font-bold text-slate-600 shadow-sm no-underline transition-all hover:-translate-y-0.5 hover:border-[#3498db] hover:text-[#1e3c72]">Next »</a>
            </div>
            @endif

        </main>
    </div>

    <script>
        function searchDDC(nomorDDC) {
            document.getElementById('keywordInput').value = nomorDDC;
            document.getElementById('searchForm').submit();
        }
    </script>
</body>
</html>
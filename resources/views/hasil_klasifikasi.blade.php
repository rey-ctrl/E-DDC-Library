<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Pencarian - E-DDC</title>
    
    <!-- Import Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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

    <body class="bg-[#f4f6f9] text-[#333] antialiased m-0">

    {{-- Navbar dengan efek Glassmorphism --}}
    <nav class="fixed start-0 top-0 z-50 w-full border-b border-white/20 bg-white/80 backdrop-blur-md shadow-sm transition-all duration-300">
        <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between p-4">
            <!-- Bagian Logo -->
            <a href="/" class="flex items-center space-x-3 transition-transform duration-300 hover:scale-105 rtl:space-x-reverse cursor-pointer">
                <img src="./logo-whitemode.png" class="h-12 w-auto drop-shadow-md" alt="Logo" />
                <span class="self-center whitespace-nowrap text-2xl font-extrabold tracking-tight text-[#1e3c72]">E-DDC<span class="text-blue-500">.</span></span>
            </a>
            
            <!-- Tombol Hamburger Mobile -->
            <button data-collapse-toggle="navbar-default" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg p-2 text-sm text-slate-500 transition-colors hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-300 md:hidden" aria-controls="navbar-default" aria-expanded="false">
                <span class="sr-only">Open main menu</span>
                <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h14"/></svg>
            </button>
            
            <!-- Bagian Menu -->
            <div class="hidden w-full md:block md:w-auto" id="navbar-default">
                <ul class="mt-4 flex flex-col items-center rounded-lg border border-slate-100 bg-slate-50 p-4 font-medium md:mt-0 md:flex-row md:space-x-8 md:border-0 md:bg-transparent md:p-0 rtl:space-x-reverse">
                    <li>
                        <!-- Menu Home Pill Button -->
                        <a href="/" class="block rounded-full bg-[#1e3c72] px-8 py-2.5 text-center text-sm font-semibold text-white shadow-lg shadow-blue-900/20 transition-all duration-300 hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-blue-900/40" aria-current="page">
                            Home
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="h-16"></div> <!-- Spacer untuk navbar -->

    <!-- Layout Utama -->
    <div class="mx-auto mt-8 flex max-w-[1200px] items-start gap-6 px-5 pb-10">
        
        <!-- SIDEBAR KIRI -->
        <aside class="sticky top-[100px] flex w-[320px] shrink-0 flex-col overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm max-h-[calc(100vh-130px)] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-track]:bg-slate-100 [&::-webkit-scrollbar-thumb]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-slate-300 hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">

            <!-- Judul Tab -->
            <div class="flex border-b border-slate-200 bg-slate-50">
                <div class="flex-1 border-b-2 border-[#1e3c72] bg-white py-2.5 pl-4 text-left text-[13px] font-semibold text-[#1e3c72]">Klesterisasi Pencarian Buku</div>
            </div>

            <!-- Area Cari Kata -->
            <div class="border-b border-slate-200 bg-white p-4 pb-3">
                <form id="searchForm" action="{{ route('klasifikasi.process') }}" method="POST">
                    @csrf
                    <label class="mb-2 block text-[12px] font-semibold text-slate-600">Ketik kata/judul untuk mencari:</label>
                    <div class="flex gap-2">
                        <input type="text" id="keywordInput" name="keyword" value="{{ request('keyword') }}" placeholder="Contoh: manajemen..." required autocomplete="off" 
                               class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-[13px] outline-none transition-colors focus:border-[#3498db]">
                        <button type="submit" class="rounded-md bg-[#f39c12] px-4 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-yellow-600">Display</button>
                    </div>
                </form>
            </div>
        
            <!-- Area Cari Angka DDC -->
            <div class="max-h-[250px] overflow-y-auto bg-white">
                <table class="w-full border-collapse text-[13px]">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="border-b border-slate-200 px-4 py-2 text-left font-semibold text-slate-600">Pilih Topik DDC (Angka)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr onclick="searchDDC('650')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">650 - 659 Manajemen</td></tr>
                        <tr onclick="searchDDC('640')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">640 - 649 Kesejahteraan</td></tr>
                        <tr onclick="searchDDC('630')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">630 - 639 Pertanian</td></tr>
                        <tr onclick="searchDDC('670')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">670 - 679 Pabrik</td></tr>
                        <tr onclick="searchDDC('330')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">330 - 339 Ilmu Ekonomi</td></tr>
                        <tr onclick="searchDDC('790')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">790 - 799 Olah Raga</td></tr>
                        <tr onclick="searchDDC('660')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">660 - 669 Teknologi Kimia</td></tr>
                        <tr onclick="searchDDC('000')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">000 - 009 Ilmu Umum</td></tr>
                        <tr onclick="searchDDC('100')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">100 - 199 Filsafat</td></tr>
                        <tr onclick="searchDDC('200')" class="cursor-pointer transition-colors hover:bg-slate-100"><td class="border-b border-slate-100 px-4 py-2 text-slate-700">200 - 299 Agama</td></tr>
                    </tbody>
                </table>
            </div>
        
            <!-- Sidebar Filters -->
            <div class="border-t border-slate-200 bg-slate-50 p-4">
                <label class="mb-2 flex cursor-pointer items-center gap-2 text-[12px] text-slate-600">
                    <input type="checkbox" class="cursor-pointer accent-[#3498db]"> Search previous results
                </label>
                <label class="mb-2 flex cursor-pointer items-center gap-2 text-[12px] text-slate-600">
                    <input type="checkbox" checked class="cursor-pointer accent-[#3498db]"> Match similar words
                </label>
                <label class="mb-2 flex cursor-pointer items-center gap-2 text-[12px] text-slate-600">
                    <input type="checkbox" class="cursor-pointer accent-[#3498db]"> Search titles only
                </label>
            </div>
        </aside>

        <!-- KONTEN KANAN -->
        <main class="flex-1">
        
            <!-- Info Bar -->
            <div class="mb-5 flex items-center justify-between rounded-lg border-l-4 border-l-[#1e3c72] bg-white px-5 py-4 text-sm shadow-sm">
                <span>Ditemukan <b class="text-[#1e3c72]">{{ count($books) }}</b> hasil untuk kata kunci: <b class="text-[#1e3c72]">"{{ request('keyword') }}"</b></span>
                <select class="cursor-pointer rounded border border-slate-300 bg-white px-3 py-1.5 text-slate-600 outline-none">
                    <option>Paling Relevan</option>
                    <option>Terbaru</option>
                </select>
            </div>
        
            <!-- Daftar Buku -->
            @forelse($books as $buku)
            <div class="mb-4 flex rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition-transform hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
            
                <!-- Cover Dummy -->
                <div class="mr-5 h-[160px] w-[110px] shrink-0 overflow-hidden rounded-md border border-slate-200 bg-slate-100">
                    <img src="https://via.placeholder.com/110x160/e2e8f0/94a3b8?text=No+Cover" alt="Cover" class="h-full w-full object-cover">
                </div>
            
                <!-- Informasi Buku -->
                <div class="flex-1">
                    <h2 class="mb-2 text-lg font-bold text-slate-800">{{ $buku['Book_Title'] ?? 'Tanpa Judul' }}</h2>
                    <div class="mb-3 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        {{ $buku['Author'] ?? 'Penulis Tidak Diketahui' }}
                    </div>
                    
                    <div class="mb-2 grid grid-cols-2 gap-2 text-[13px] text-slate-500">
                        <div>Tahun: <b class="text-slate-700">{{ $buku['Year_Published'] ?? '-' }}</b></div>
                        <div>Halaman: <b class="text-slate-700">{{ $buku['Pages'] ?? '-' }}</b></div>
                        <div>Alamat: <b class="text-slate-700">{{ $buku['Alamat'] ?? '-' }}</b></div>
                    </div>

                    <div class="mt-2 border-t border-dashed border-slate-200 pt-2.5 text-[13px] leading-relaxed text-slate-600">
                        Koleksi perpustakaan tersedia untuk sirkulasi. ID Peminjaman terakhir: <b class="text-slate-700">{{ $buku['ID_Peminjaman'] ?? '-' }}</b>.
                    </div>
                </div>

                <!-- Kode DDC / Aksi -->
                <div class="ml-5 flex w-[140px] shrink-0 flex-col justify-center border-l border-slate-200 pl-5 text-center">
                    <div class="mb-3 rounded-md border border-slate-300 bg-slate-50 p-2.5">
                        <div class="text-[10px] font-semibold uppercase text-slate-500">Kode Buku</div>
                        <div class="mt-0.5 text-2xl font-extrabold text-slate-900">{{ $buku['Book_Code'] ?? '000' }}</div>
                    </div>
                    <button class="mb-2 block w-full rounded-md border border-[#1e3c72] bg-white py-2 text-xs font-semibold text-[#1e3c72] transition-colors hover:bg-[#1e3c72] hover:text-white">Detail Buku</button>
                    <button class="mb-2 block w-full rounded-md border border-[#1e3c72] bg-white py-2 text-xs font-semibold text-[#1e3c72] transition-colors hover:bg-[#1e3c72] hover:text-white">Sitasi MARC</button>
                </div>
            </div>
            
            @empty
            <div class="flex flex-col items-center justify-center rounded-lg bg-white px-5 py-16 text-center shadow-sm">
                <h3 class="mb-2 text-lg font-bold text-red-500">Buku Tidak Ditemukan</h3>
                <p class="text-[14px] text-slate-500">Maaf, tidak ada klasifikasi atau buku yang cocok dengan kata kunci <b class="text-slate-700">"{{ request('keyword') }}"</b>.</p>
            </div>
            @endforelse

            <!-- Pagination Bar -->
            @if(count($books) > 0)
            <div class="mb-5 mt-8 flex items-center justify-center gap-2">
                <a href="#" class="pointer-events-none rounded-md border border-slate-200 bg-slate-50 px-3.5 py-2 text-[13px] font-medium text-slate-400 no-underline">« Prev</a>
                <a href="#" class="rounded-md border border-[#1e3c72] bg-[#1e3c72] px-3.5 py-2 text-[13px] font-medium text-white no-underline">1</a>
                <a href="#" class="rounded-md border border-slate-300 bg-white px-3.5 py-2 text-[13px] font-medium text-slate-600 no-underline transition-colors hover:border-slate-400 hover:bg-slate-100 hover:text-[#1e3c72]">2</a>
                <a href="#" class="rounded-md border border-slate-300 bg-white px-3.5 py-2 text-[13px] font-medium text-slate-600 no-underline transition-colors hover:border-slate-400 hover:bg-slate-100 hover:text-[#1e3c72]">3</a>
                <span class="pointer-events-none rounded-md border border-slate-200 bg-slate-50 px-3.5 py-2 text-[13px] font-medium text-slate-400">...</span>
                <a href="#" class="rounded-md border border-slate-300 bg-white px-3.5 py-2 text-[13px] font-medium text-slate-600 no-underline transition-colors hover:border-slate-400 hover:bg-slate-100 hover:text-[#1e3c72]">Next »</a>
            </div>
            @endif

        </main>
    </div>

    <!-- Script agar klik tabel memicu pencarian angka -->
    <script>
        function searchDDC(nomorDDC) {
            document.getElementById('keywordInput').value = nomorDDC;
            document.getElementById('searchForm').submit();
        }
    </script>
</body>
</html>
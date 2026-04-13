<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-DDC | Sistem Klasifikasi Perpustakaan</title>
    
    <!-- Import Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Import Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Konfigurasi Tailwind Kustom -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased selection:bg-blue-200 selection:text-blue-900">

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

    <!-- Hero Section Keren dengan Animasi Gradient -->
    <section class="relative overflow-hidden bg-slate-900 px-6 py-32 text-center sm:py-40">
        <!-- Dekorasi Latar Belakang (Blobs) -->
        <div class="absolute -top-40 left-0 h-96 w-96 animate-blob rounded-full bg-blue-700 opacity-30 mix-blend-multiply blur-[100px] filter"></div>
        <div class="absolute -right-20 top-20 h-96 w-96 animate-blob rounded-full bg-orange-500 opacity-20 mix-blend-multiply blur-[100px] filter animation-delay-2000"></div>
        <div class="absolute -bottom-40 left-1/2 h-96 w-96 animate-blob rounded-full bg-cyan-500 opacity-20 mix-blend-multiply blur-[100px] filter animation-delay-4000"></div>

        <div class="relative z-10 mx-auto max-w-4xl">
            <h1 class="mb-6 text-5xl font-extrabold tracking-tight text-white sm:text-6xl lg:text-7xl">
                Jelajahi <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">Katalog Buku</span>
            </h1>
            <p class="mx-auto mb-10 max-w-2xl text-lg font-medium text-slate-300 sm:text-xl">
                Gunakan mesin pencari berbasis Dewey Decimal Classification (DDC) untuk menemukan koleksi perpustakaan dengan cepat dan akurat.
            </p>
            
            <!-- Form Pencarian Glassmorphism -->
            <form id="searchForm" action="{{ route('klasifikasi.process') }}" method="POST" class="mx-auto flex w-full max-w-3xl items-center rounded-full border border-white/20 bg-white/10 p-2 shadow-2xl backdrop-blur-md focus-within:ring-4 focus-within:ring-blue-500/30 transition-all duration-300">
                @csrf
                <div class="pl-6 text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input id="searchInput" type="text" name="keyword" placeholder="Masukkan Judul, Pengarang, atau Kode DDC (Misal: 911)..." required autocomplete="off" 
                       class="w-full bg-transparent px-4 py-3 text-lg text-white placeholder-slate-300/70 outline-none">
                <button type="submit" class="shrink-0 cursor-pointer rounded-full bg-gradient-to-r from-[#f39c12] to-orange-500 px-8 py-4 text-base font-bold text-white shadow-lg transition-transform duration-300 hover:scale-105 active:scale-95">
                    Cari Koleksi
                </button>
            </form>
        </div>
    </section>

    <div class="mb-4 bg-white relative mx-auto -mt-16 max-w-[1400px] px-6 pb-24 z-20" >  </div>

    <!-- 10 Kelas Utama DDC (Grid Ultra Modern) -->
    <section class="bg-white relative mx-auto -mt-16 max-w-[1400px] px-6 pb-24 z-20">
        <div class="mb-12 text-center">
            <h2 class="inline-block text-3xl font-black text-slate-800 tracking-tight sm:text-4xl relative">
                10 Kelas Utama DDC
                <div class="absolute -bottom-2 left-1/2 h-1.5 w-1/2 -translate-x-1/2 rounded-full bg-[#3498db]"></div>
            </h2>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            
            <!-- Fungsi Reusable untuk Kartu agar kodenya bersih -->
            <!-- Card 000 -->
            <div onclick="searchDDC('000')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <!-- Animasi Garis Top Border -->
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <!-- Angka Watermark Background -->
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">000</div>
                <!-- Konten Inti -->
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">000</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Komputer, Informasi & Referensi Umum</div>
                </div>
            </div>

            <!-- Card 100 -->
            <div onclick="searchDDC('100')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">100</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">100</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Filsafat & Psikologi</div>
                </div>
            </div>

            <!-- Card 200 -->
            <div onclick="searchDDC('200')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">200</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">200</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Agama</div>
                </div>
            </div>

            <!-- Card 300 -->
            <div onclick="searchDDC('300')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">300</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">300</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Ilmu Sosial</div>
                </div>
            </div>

            <!-- Card 400 -->
            <div onclick="searchDDC('400')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">400</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">400</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Bahasa</div>
                </div>
            </div>

            <!-- Card 500 -->
            <div onclick="searchDDC('500')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">500</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">500</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Sains & Matematika</div>
                </div>
            </div>

            <!-- Card 600 -->
            <div onclick="searchDDC('600')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">600</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">600</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Teknologi & Ilmu Terapan</div>
                </div>
            </div>

            <!-- Card 700 -->
            <div onclick="searchDDC('700')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">700</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">700</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Kesenian & Rekreasi</div>
                </div>
            </div>

            <!-- Card 800 -->
            <div onclick="searchDDC('800')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">800</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">800</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Sastra & Literatur</div>
                </div>
            </div>

            <!-- Card 900 -->
            <div onclick="searchDDC('900')" class="group relative cursor-pointer overflow-hidden rounded-2xl bg-white p-6 shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl border border-slate-100">
                <div class="absolute left-0 top-0 h-1.5 w-0 bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-500 group-hover:w-full"></div>
                <div class="absolute -bottom-6 -right-2 text-[120px] font-black leading-none text-slate-50 transition-colors duration-500 group-hover:text-blue-50/80">900</div>
                <div class="relative z-10">
                    <div class="mb-2 text-4xl font-extrabold text-[#1e3c72] transition-colors duration-300 group-hover:text-[#f39c12]">900</div>
                    <div class="text-sm font-bold leading-snug text-slate-600 group-hover:text-slate-900">Sejarah & Geografi</div>
                </div>
            </div>

        </div>
    </section>

    <!-- Footer Super Minimalis -->
    <footer class="border-t border-slate-200 bg-white py-8 text-center text-sm font-medium text-slate-500">
        &copy; 2026 E-DDC Library System. Dikembangkan untuk keperluan klasifikasi pustaka.
    </footer>

    <!-- Script JavaScript untuk fungsi klik kartu DDC -->
    <script>
        function searchDDC(kodeDDC) {
            // Mengisi input pencarian dengan kode DDC dari kartu yang diklik
            document.getElementById('searchInput').value = kodeDDC;
            
            // Mengirimkan (submit) form secara otomatis
            document.getElementById('searchForm').submit();
        }
    </script>
</body>
</html>
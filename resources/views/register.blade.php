<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-DDC | Sistem Klasifikasi Perpustakaan</title>
    <meta name="description" content="Daftar akun E-DDC baru untuk mengelola koleksi perpustakaan.">

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
                        'blob': 'blob 7s infinite',
                        'fade-in-up': 'fadeInUp 0.6s ease both',
                    },
                    keyframes: {
                        blob: {
                            '0%':   { transform: 'translate(0px, 0px) scale(1)' },
                            '33%':  { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%':  { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        fadeInUp: {
                            '0%':   { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                    }
                }
            }
        }
    </script>

    <style>
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="bg-slate-900 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center relative overflow-hidden py-12">

    <!-- Dekorasi Blobs -->
    <div class="absolute -top-40 left-0 h-96 w-96 animate-blob rounded-full bg-blue-700 opacity-30 mix-blend-multiply blur-[100px] filter"></div>
    <div class="absolute -right-20 top-20 h-96 w-96 animate-blob rounded-full bg-orange-500 opacity-20 mix-blend-multiply blur-[100px] filter animation-delay-2000"></div>
    <div class="absolute -bottom-40 left-1/2 h-96 w-96 animate-blob rounded-full bg-cyan-500 opacity-20 mix-blend-multiply blur-[100px] filter animation-delay-4000"></div>

    <div class="relative z-10 w-full max-w-md px-6 animate-fade-in-up">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center space-x-3 transition-transform duration-300 hover:scale-105">
                <img src="/logo-whitemode.png" class="h-14 w-auto drop-shadow-lg brightness-0 invert" alt="Logo" />
                <span class="text-3xl font-extrabold tracking-tight text-white">E-DDC<span class="text-blue-400">.</span></span>
            </a>
            <p class="mt-3 text-sm font-medium text-slate-400">Daftar Akun Sistem Klasifikasi Perpustakaan</p>
        </div>

        <!-- Register Card -->
        <div class="rounded-2xl border border-white/10 bg-white/[0.07] p-8 shadow-2xl backdrop-blur-xl">
            
            @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-300">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    {{ $errors->first() }}
                </div>
            </div>
            @endif

            <form action="{{ route('register.process') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Nama Lengkap -->
                <div>
                    <label for="name" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-400">Nama Lengkap</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                               placeholder="Nama Lengkap Anda"
                               class="w-full rounded-xl border border-white/10 bg-white/5 py-3 pl-12 pr-4 text-sm text-white placeholder-slate-500 outline-none transition-all duration-300 focus:border-blue-500/50 focus:bg-white/10 focus:ring-4 focus:ring-blue-500/10">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-400">Email</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               placeholder="nama@email.com"
                               class="w-full rounded-xl border border-white/10 bg-white/5 py-3 pl-12 pr-4 text-sm text-white placeholder-slate-500 outline-none transition-all duration-300 focus:border-blue-500/50 focus:bg-white/10 focus:ring-4 focus:ring-blue-500/10">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-400">Password</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <input id="password" type="password" name="password" required
                               placeholder="Min. 8 karakter"
                               class="w-full rounded-xl border border-white/10 bg-white/5 py-3 pl-12 pr-4 text-sm text-white placeholder-slate-500 outline-none transition-all duration-300 focus:border-blue-500/50 focus:bg-white/10 focus:ring-4 focus:ring-blue-500/10">
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div>
                    <label for="password_confirmation" class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-400">Konfirmasi Password</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                               placeholder="Ulangi password"
                               class="w-full rounded-xl border border-white/10 bg-white/5 py-3 pl-12 pr-4 text-sm text-white placeholder-slate-500 outline-none transition-all duration-300 focus:border-blue-500/50 focus:bg-white/10 focus:ring-4 focus:ring-blue-500/10">
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full rounded-xl bg-gradient-to-r from-[#1e3c72] to-blue-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition-all duration-300 hover:-translate-y-0.5 hover:from-blue-800 hover:to-blue-700 hover:shadow-blue-900/50 active:scale-[0.98]">
                    Daftar
                </button>
            </form>

            <!-- Link to Login -->
            <div class="mt-6 text-center text-xs">
                <span class="text-slate-400">Sudah punya akun?</span>
                <a href="{{ route('login') }}" class="font-semibold text-blue-400 hover:text-blue-300 transition-colors ml-1">Masuk sekarang</a>
            </div>
        </div>

        <!-- Footer -->
        <p class="mt-6 text-center text-[12px] text-slate-500">
            &copy; 2026 E-DDC Library System
        </p>
    </div>

</body>
</html>

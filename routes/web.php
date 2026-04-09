<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Menampilkan halaman utama (form search)
Route::get('/', function () {
    return view('klasifikasi'); // Pastikan kamu punya file klasifikasi.blade.php
})->name('klasifikasi.index');

// Route untuk memproses klasifikasi (menghubungkan ke API Python)
Route::post('/klasifikasi', function (Request $request) {
    $keyword = $request->input('keyword');

    try {
        // Panggil API Python
        $response = Http::get("http://127.0.0.1:5000/api/buku/search", [
            'keyword' => $keyword
        ]);

        // Jika sukses ambil json-nya, jika gagal set array kosong
        $data = $response->successful() ? $response->json() : [];
        
    } catch (\Exception $e) {
        // Jika server Python mati, set array kosong agar tidak error merah
        $data = [];
    }

    // Pastikan $data selalu berbentuk array sebelum dikirim ke view
    return view('hasil_klasifikasi', ['books' => $data ?? []]);
})->name('klasifikasi.process');
<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// ─── Halaman Utama ────────────────────────────────────────────────
Route::get('/', function () {
    return view('klasifikasi');
})->name('klasifikasi.index');

// ─── Proses Klasifikasi (GET & POST) ──────────────────────────────
Route::match(['get', 'post'], '/klasifikasi', function (Request $request) {
    $keyword = trim($request->input('keyword', ''));
    $filters = $request->input('filters', []);

    $books    = [];
    $apiError = false;

    if ($keyword !== '' || !empty($filters)) {
        try {
            $response = Http::timeout(10)->get('http://127.0.0.1:5000/api/buku/search', [
                'keyword' => $keyword,
                'filters' => is_array($filters) ? implode(',', $filters) : $filters
            ]);

            if ($response->successful()) {
                $json = $response->json();
                // API mengembalikan array buku langsung
                $books = is_array($json) ? $json : [];
            }
        } catch (\Exception $e) {
            // Server Python tidak aktif — tampilkan pesan ramah
            $apiError = true;
        }
    }

    return view('hasil_klasifikasi', [
        'books'    => $books,
        'keyword'  => $keyword,
        'apiError' => $apiError,
    ]);
})->name('klasifikasi.process');

// ─── Detail Buku (AJAX / direct link) ────────────────────────────
Route::get('/buku/{id}', function ($id) {
    try {
        $response = Http::timeout(10)->get("http://127.0.0.1:5000/api/buku/detail/{$id}");
        $buku = $response->successful() ? $response->json() : null;
    } catch (\Exception $e) {
        $buku = null;
    }

    return response()->json($buku);
})->name('buku.detail');
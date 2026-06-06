<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\AuthController;

// ─── Halaman Utama ────────────────────────────────────────────────
Route::get('/', function () {
    return view('klasifikasi');
})->name('klasifikasi.index');

// ─── Auth Routes ──────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ─── Tambah Buku (perlu login) ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/buku/tambah', function () {
        return view('tambah_buku');
    })->name('buku.tambah');

    Route::post('/buku/tambah', function (Request $request) {
        $request->validate([
            'title'          => 'required|string|max:500',
            'sor'            => 'required|string|max:300',
            'classification' => 'required|string|max:50',
            'publish_year'   => 'nullable|string|max:10',
            'isbn_issn'      => 'nullable|string|max:100',
            'call_number'    => 'nullable|string|max:100',
            'edition'        => 'nullable|string|max:100',
            'collation'      => 'nullable|string|max:200',
            'series_title'   => 'nullable|string|max:300',
            'spec_detail_info' => 'nullable|string|max:5000',
            'notes'          => 'nullable|string|max:5000',
        ]);

        try {
            $response = Http::timeout(10)->post('http://127.0.0.1:5000/api/buku/store', [
                'title'            => $request->input('title'),
                'sor'              => $request->input('sor'),
                'publish_year'     => $request->input('publish_year', ''),
                'isbn_issn'        => $request->input('isbn_issn', ''),
                'classification'   => $request->input('classification'),
                'call_number'      => $request->input('call_number', ''),
                'edition'          => $request->input('edition', ''),
                'collation'        => $request->input('collation', ''),
                'series_title'     => $request->input('series_title', ''),
                'spec_detail_info' => $request->input('spec_detail_info', ''),
                'notes'            => $request->input('notes', ''),
            ]);

            if ($response->successful()) {
                $json = $response->json();
                return redirect()->route('buku.tambah')
                    ->with('success', $json['message'] ?? 'Buku berhasil ditambahkan.');
            } else {
                $json = $response->json();
                return back()->withInput()
                    ->with('error', $json['error'] ?? 'Gagal menyimpan buku ke database.');
            }
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Server AI tidak aktif. Pastikan python api.py sudah berjalan.');
        }
    })->name('buku.store');
});

// ─── Proses Klasifikasi (GET & POST) ──────────────────────────────
Route::match(['get', 'post'], '/klasifikasi', function (Request $request) {
    $keyword    = trim($request->input('keyword', ''));
    $filters    = $request->input('filters', []);
    $filterMode = $request->input('filter_mode', 'and');
    $page       = $request->input('page', 1);
    $mode       = $request->input('mode', 'database');
    if (!auth()->check()) {
        $mode = 'database';
    }

    $books      = [];
    $pagination = null;
    $stats      = null;
    $apiError   = false;

    try {
        $response = Http::timeout(10)->get('http://127.0.0.1:5000/api/buku/search', [
            'keyword'     => $keyword,
            'filters'     => is_array($filters) ? implode(',', $filters) : $filters,
            'filter_mode' => $filterMode,
            'page'        => $page,
            'mode'        => $mode
        ]);

        if ($response->successful()) {
            $json = $response->json();
            if (isset($json['data']) && isset($json['pagination'])) {
                $books      = $json['data'];
                $pagination = $json['pagination'];
                $stats      = $json['stats'] ?? null;
            } else {
                $books = is_array($json) ? $json : [];
            }
        }
    } catch (\Exception $e) {
        $apiError = true;
    }

    return view('hasil_klasifikasi', [
        'books'       => $books,
        'keyword'     => $keyword,
        'filters'     => $filters,
        'filterMode'  => $filterMode,
        'pagination'  => $pagination,
        'apiError'    => $apiError,
        'mode'        => $mode,
        'stats'       => $stats,
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
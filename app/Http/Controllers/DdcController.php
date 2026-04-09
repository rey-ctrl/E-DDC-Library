<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DdcController extends Controller
{
    // Menampilkan halaman utama website
    public function index()
    {
        return view('klasifikasi');
    }

    // Memproses data dan mengirimkannya ke Python
    public function process(Request $request)
    {
        // 1. Validasi inputan dari form
        $validated = $request->validate([
            'judul_buku' => 'required|string|max:255',
        ]);

        $judul = $validated['judul_buku'];

        try {
            // 2. Mengirim request ke API Python (pastikan Flask/FastAPI berjalan di port 5000)
            $response = Http::timeout(10)->post('http://127.0.0.1:5000/predict', [
                'text' => $judul
            ]);

            // 3. Menangani balasan dari Python
            if ($response->successful()) {
                $hasil = $response->json();
                
                return view('klasifikasi', [
                    'judul_buku' => $judul,
                    'ddc_code' => $hasil['ddc_code'] ?? 'Tidak ditemukan'
                ]);
            }

            return back()->with('error', 'Gagal memproses klasifikasi di server Python.');

        } catch (\Exception $e) {
            // Menangkap error jika server Python belum dinyalakan
            return back()->with('error', 'Koneksi ke server AI terputus. Pastikan server Python sudah berjalan.');
        }
    }
}
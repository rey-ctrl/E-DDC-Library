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

    public function edit($id)
    {
        try {
            $response = Http::timeout(10)->get("http://127.0.0.1:5000/api/buku/detail/{$id}");
            if ($response->successful()) {
                $buku = $response->json();
                return view('edit_buku', compact('buku'));
            }
            return redirect()->route('klasifikasi.process')->with('error', 'Buku tidak ditemukan.');
        } catch (\Exception $e) {
            return redirect()->route('klasifikasi.process')->with('error', 'Server AI tidak aktif.');
        }
    }

    public function update(Request $request, $id)
    {
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
            $response = Http::timeout(10)->put("http://127.0.0.1:5000/api/buku/update/{$id}", [
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
                return redirect()->route('klasifikasi.process')
                    ->with('success', $json['message'] ?? 'Buku berhasil diperbarui.');
            } else {
                $json = $response->json();
                return back()->withInput()
                    ->with('error', $json['error'] ?? 'Gagal memperbarui buku.');
            }
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Server AI tidak aktif. Gagal memperbarui.');
        }
    }

    public function destroy($id)
    {
        try {
            $response = Http::timeout(10)->delete("http://127.0.0.1:5000/api/buku/delete/{$id}");
            if ($response->successful()) {
                return redirect()->back()
                    ->with('success', 'Buku berhasil dihapus.');
            }
            return redirect()->back()->with('error', 'Gagal menghapus buku.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Server AI tidak aktif. Gagal menghapus.');
        }
    }
}
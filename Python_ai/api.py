from flask import Flask, jsonify, request
from flask_cors import CORS
import pandas as pd
import os

app = Flask(__name__)
CORS(app) # Mengizinkan akses dari Laravel

# Pastikan file CSV kamu bernama 'data.csv' dan berada di folder yang sama
CSV_FILE = 'data.csv' 

@app.route('/api/buku/search', methods=['GET'])
def search_buku():
    # 1. Cek apakah file CSV ada
    if not os.path.exists(CSV_FILE):
        print(f"Peringatan: File {CSV_FILE} tidak ditemukan!")
        return jsonify([]) # Kirim array kosong jika file tidak ditemukan

    try:
        # 2. Ambil keyword dari URL (dikirim dari Laravel)
        keyword = request.args.get('keyword', '').strip()
        
        # 3. Baca file CSV
        df = pd.read_csv(CSV_FILE)
        
        # Bersihkan data: Abaikan baris yang datanya kosong (NaN) agar tidak memicu error
        df = df.dropna(subset=['Book_Title', 'Author', 'Book_Code'])

        # 4. Proses Filter / Pencarian
        if keyword:
            # Mencari kecocokan di kolom Judul, Pengarang, ATAU Kode Buku (DDC)
            # astype(str) digunakan agar angka DDC diubah jadi teks saat dicari
            mask = (
                df['Book_Title'].astype(str).str.contains(keyword, case=False, na=False) | 
                df['Author'].astype(str).str.contains(keyword, case=False, na=False) |
                df['Book_Code'].astype(str).str.contains(keyword, case=False, na=False)
            )
            filtered_df = df[mask]
        else:
            # Jika user klik tombol cari tanpa mengetik apa-apa, tampilkan semua
            filtered_df = df

        # 5. Batasi hasil maksimal 100 data agar website tidak berat (bisa disesuaikan)
        results = filtered_df.head(100).to_dict(orient='records')
        
        # Kirim hasil ke Laravel
        return jsonify(results)

    except Exception as e:
        # Tampilkan pesan error di terminal Python jika terjadi masalah
        print(f"Error di server Python: {e}")
        return jsonify([]) 

if __name__ == '__main__':
    print("=======================================")
    print("  SERVER API E-DDC SEDANG BERJALAN")
    print("  Menunggu pencarian dari Laravel...")
    print("=======================================")
    app.run(debug=True, port=5000)
import pandas as pd
import numpy as np
from sqlalchemy import create_engine
import skfuzzy as fuzz
import pickle
import re
import os

# Load .env jika ada
try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass

print("=" * 55)
print("  E-DDC - Training Model Fuzzy C-Means")
print("=" * 55)

# ─────────────────────────────────────────────
# 1. Koneksi ke Database
# ─────────────────────────────────────────────
print("\n[1/4] Menghubungkan ke database MySQL (opac)...")
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine  = create_engine(DB_URL)

query = """
    SELECT title AS judul_buku, classification AS kode_ddc 
    FROM biblio 
    WHERE classification IS NOT NULL 
      AND classification != ''
      AND classification != 'NONE'
      AND opac_hide = 0
"""
df = pd.read_sql(query, engine)
print(f"    -> Berhasil menarik {len(df)} baris data buku.")

# ─────────────────────────────────────────────
# 2. Bersihkan Data DDC
# ─────────────────────────────────────────────
print("\n[2/4] Membersihkan kode DDC...")

def clean_ddc(text):
    """Ambil 3 digit angka pertama dari kode DDC."""
    match = re.search(r'(\d{3})', str(text).strip())
    if match:
        return int(match.group(1))
    return None

df['ddc_bersih'] = df['kode_ddc'].apply(clean_ddc)
df = df.dropna(subset=['ddc_bersih'])
df['ddc_bersih'] = df['ddc_bersih'].astype(int)

# Buang DDC di luar range DDC standar (0–999)
df = df[(df['ddc_bersih'] >= 0) & (df['ddc_bersih'] <= 999)]

print(f"    -> Data bersih: {len(df)} baris siap dilatih.")
print(f"    -> Contoh DDC unik: {sorted(df['ddc_bersih'].unique()[:15].tolist())}...")

# ─────────────────────────────────────────────
# 3. Latih Fuzzy C-Means
# ─────────────────────────────────────────────
print("\n[3/4] Melatih model Fuzzy C-Means...")

# Jumlah klaster = 10 (Agar mencakup seluruh spektrum 10 kelas utama DDC dengan lebih akurat)
N_CLUSTERS = 10

# Format data: scikit-fuzzy butuh array 2D (features x samples)
alldata = np.vstack((
    df['ddc_bersih'].values.astype(float),
    np.zeros(len(df))
))

# Jalankan FCM
cntr, u, u0, d, jm, p, fpc = fuzz.cluster.cmeans(
    alldata,
    c=N_CLUSTERS,
    m=2.0,
    error=0.005,
    maxiter=1000,
    init=None
)

print(f"    -> Selesai! FPC (Fuzzy Partition Coefficient) = {fpc:.4f}")
print(f"    -> Semakin mendekati 1.0 = klaster semakin baik.")

# ─────────────────────────────────────────────
# 4. Simpan Model
# ─────────────────────────────────────────────
print("\n[4/4] Menyimpan model ke FUZZY_CENTERS.pickle...")
import os
save_path = os.path.join(os.path.dirname(__file__), 'FUZZY_CENTERS.pickle')
with open(save_path, 'wb') as f:
    pickle.dump(cntr, f)

print(f"    -> Model disimpan di: {save_path}")

# ─────────────────────────────────────────────
# Preview Hasil Multilabel (10 buku pertama)
# ─────────────────────────────────────────────
print("\n" + "=" * 55)
print("  PREVIEW HASIL MULTILABEL (10 Buku Pertama)")
print("=" * 55)

for i in range(min(10, len(df))):
    judul = df['judul_buku'].iloc[i]
    ddc   = int(df['ddc_bersih'].iloc[i])
    print(f"\nBuku #{i+1}: {judul[:60]}...")
    print(f"   DDC  : {ddc}")
    
    # Urutkan klaster berdasarkan probabilitas tertinggi
    probs = [(klaster_idx, u[klaster_idx][i] * 100) for klaster_idx in range(N_CLUSTERS)]
    probs.sort(key=lambda x: x[1], reverse=True)
    
    for klaster_idx, persen in probs:
        bar = "#" * int(persen / 5)
        print(f"   Klaster {klaster_idx}: {persen:5.1f}%  {bar}")

print("\n[OK] Training selesai. Jalankan 'python api.py' untuk memulai server.")
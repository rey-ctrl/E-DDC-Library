import pandas as pd
import numpy as np
from sqlalchemy import create_engine
from sklearn.metrics import classification_report, accuracy_score, precision_recall_fscore_support
import pickle
import re
import os
import warnings
warnings.filterwarnings('ignore')

try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass

print("=" * 65)
# E-DDC Model Evaluation Script
print("  E-DDC - Evaluasi Performa Model AI (F1-Score, Precision, Recall)")
print("=" * 65)

# ─────────────────────────────────────────────
# 1. Load Model & Info
# ─────────────────────────────────────────────
MODEL_DIR = os.path.dirname(__file__)
HYBRID_PATH = os.path.join(MODEL_DIR, 'MODEL_HYBRID.pickle')

if not os.path.exists(HYBRID_PATH):
    print(f"[ERROR] Model tidak ditemukan di: {HYBRID_PATH}")
    print("Harap jalankan training terlebih dahulu: python train_model.py")
    exit(1)

print("[1/4] Memuat model E-DDC...")
with open(HYBRID_PATH, 'rb') as f:
    model_data = pickle.load(f)

tfidf = model_data.get('tfidf')
clf = model_data.get('clf')
JURUSAN_LIST = model_data.get('jurusan_list')

print(f"    -> Model berhasil dimuat.")
print(f"    -> Kelas Jurusan: {len(JURUSAN_LIST)}")

# ─────────────────────────────────────────────
# 2. Koneksi ke Database & Tarik Data Aktual
# ─────────────────────────────────────────────
print("\n[2/4] Menghubungkan ke database MySQL...")
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine  = create_engine(DB_URL)

query = """
    SELECT 
        title AS judul_buku, 
        classification AS kode_ddc,
        spec_detail_info AS deskripsi,
        notes AS catatan
    FROM biblio 
    WHERE classification IS NOT NULL 
      AND classification != ''
      AND classification != 'NONE'
      AND opac_hide = 0
"""
df = pd.read_sql(query, engine)
print(f"    -> Berhasil menarik {len(df)} baris data buku.")

# ─────────────────────────────────────────────
# Mapping DDC -> Jurusan PNJ
# ─────────────────────────────────────────────
def ddc_to_jurusan(kode_ddc_raw):
    s = str(kode_ddc_raw).strip()
    m = re.search(r'(\d{3})(?:\.(\d+))?', s)
    if not m:
        return "Umum"
    main = int(m.group(1))
    sub = m.group(2) or ""

    if main <= 99:
        return "Teknik Informatika & Komputer"
    elif main <= 199:
        return "Umum"
    elif main <= 299:
        return "Umum"
    elif main <= 399:
        if main in (332, 336):
            return "Akuntansi"
        elif 370 <= main <= 379:
            return "Umum"
        return "Administrasi Niaga"
    elif main <= 499:
        return "Umum"
    elif main <= 599:
        if 510 <= main <= 519:
            return "Matematika"
        elif 530 <= main <= 539:
            return "Teknik Elektro"
        elif 540 <= main <= 549:
            return "Teknik Sipil"
        return "Sains"
    elif main <= 699:
        if main <= 609:
            return "Teknik Mesin"
        elif main <= 619:
            return "Umum"
        elif main == 620:
            return "Teknik Mesin"
        elif main == 621:
            if sub.startswith('3'):
                return "Teknik Elektro"
            return "Teknik Mesin"
        elif main <= 623:
            return "Teknik Mesin"
        elif main <= 629:
            return "Teknik Sipil"
        elif main <= 649:
            return "Umum"
        elif main <= 656:
            return "Administrasi Niaga"
        elif main == 657:
            return "Akuntansi"
        elif main <= 659:
            return "Administrasi Niaga"
        elif main <= 669:
            return "Teknik Sipil"
        elif main <= 685:
            return "Teknik Mesin"
        elif main == 686:
            return "Teknik Grafika & Penerbitan"
        elif main <= 689:
            return "Teknik Mesin"
        else:
            return "Teknik Sipil"
    elif main <= 799:
        if main <= 779:
            return "Teknik Grafika & Penerbitan"
        return "Umum"
    elif main <= 899:
        return "Umum"
    else:
        return "Umum"

def clean_ddc(text):
    match = re.search(r'(\d{3})', str(text).strip())
    if match:
        return int(match.group(1))
    return None

df['ddc_bersih'] = df['kode_ddc'].apply(clean_ddc)
df = df.dropna(subset=['ddc_bersih'])
df['jurusan_ddc'] = df['kode_ddc'].apply(ddc_to_jurusan)

def combine_text(row):
    parts = []
    for col in ['judul_buku', 'deskripsi', 'catatan']:
        val = str(row.get(col, '')).strip()
        if val and val.lower() not in ('', 'null', 'none', 'nan'):
            parts.append(val)
    return ' '.join(parts) if parts else ''

df['teks_gabungan'] = df.apply(combine_text, axis=1)
df = df[df['teks_gabungan'].str.len() > 3]

# ─────────────────────────────────────────────
# 3. Prediksi & Evaluasi Model
# ─────────────────────────────────────────────
print("\n[3/4] Melakukan klasifikasi teks menggunakan model...")

X_tfidf = tfidf.transform(df['teks_gabungan'])
y_true = df['jurusan_ddc'].values

# Prediksi menggunakan classifier
y_pred = clf.predict(X_tfidf)

# F1-Score & Metrics calculations
acc = accuracy_score(y_true, y_pred)
precision_mac, recall_mac, f1_mac, _ = precision_recall_fscore_support(y_true, y_pred, average='macro')
precision_wei, recall_wei, f1_wei, _ = precision_recall_fscore_support(y_true, y_pred, average='weighted')

print(f"    -> Selesai mengklasifikasi {len(y_true)} data buku.")

# ─────────────────────────────────────────────
# 4. Tampilkan Laporan Evaluasi
# ─────────────────────────────────────────────
print("\n[4/4] Ringkasan Metrik Evaluasi Model AI:")
print("-" * 55)
print(f"{'Metrik':<25} | {'Skor (%)':>15}")
print("-" * 55)
print(f"{'Akurasi Global (Accuracy)':<25} | {acc*100:13.2f}%")
print(f"{'Precision (Macro)':<25} | {precision_mac*100:13.2f}%")
print(f"{'Recall (Macro)':<25} | {recall_mac*100:13.2f}%")
print(f"{'F1-Score (Macro)':<25} | {f1_mac*100:13.2f}%")
print(f"{'Precision (Weighted)':<25} | {precision_wei*100:13.2f}%")
print(f"{'Recall (Weighted)':<25} | {recall_wei*100:13.2f}%")
print(f"{'F1-Score (Weighted)':<25} | {f1_wei*100:13.2f}%")
print("-" * 55)

print("\n" + "="*65)
print("  LAPORAN KLASIFIKASI MENDETAIL (Classification Report)")
print("="*65)
report_str = classification_report(y_true, y_pred)
print(report_str)

# Simpan laporan evaluasi ke file
report_path = os.path.join(MODEL_DIR, 'eval_report.txt')
with open(report_path, 'w', encoding='utf-8') as f:
    f.write("=" * 65 + "\n")
    f.write("  E-DDC - Laporan Evaluasi Performa Model AI (F1-Score)\n")
    f.write("=" * 65 + "\n\n")
    f.write(f"Total Sampel Evaluasi : {len(y_true)} buku\n")
    f.write(f"Akurasi Global        : {acc*100:.2f}%\n")
    f.write(f"F1-Score (Macro)      : {f1_mac*100:.2f}%\n")
    f.write(f"F1-Score (Weighted)   : {f1_wei*100:.2f}%\n\n")
    f.write("=== Detail per Jurusan (Precision, Recall, F1-Score) ===\n")
    f.write(report_str)

print(f"\n[OK] Laporan lengkap disimpan di: {report_path}")
print("=" * 65)

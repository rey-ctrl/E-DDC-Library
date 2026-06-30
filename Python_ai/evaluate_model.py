import pandas as pd
import numpy as np
from sqlalchemy import create_engine
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, accuracy_score, precision_recall_fscore_support
import pickle
import re
import os
import warnings
warnings.filterwarnings('ignore')

try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'), override=True)
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'), override=True)
except ImportError:
    pass

print("=" * 65)
# E-DDC Model Evaluation Script
print("  E-DDC - Evaluasi Performa Model AI")
print("  (Hold-out Test Set - Label DDC Asli)")
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

# model memiliki metrik test set
has_test_metrics = 'accuracy_test' in model_data

print(f"    -> Model berhasil dimuat.")
print(f"    -> Kelas Jurusan: {len(JURUSAN_LIST)}")

if has_test_metrics:
    print(f"    -> Format model: BARU (dengan metrik test set)")
    print(f"    -> Data training: {model_data.get('n_train', '?')} buku")
    print(f"    -> Data test    : {model_data.get('n_test', '?')} buku")
else:
    print(f"    -> Format model: LAMA (tanpa metrik test set)")
    print(f"    -> Akan melakukan evaluasi ulang dengan train/test split.")

# ─────────────────────────────────────────────
# 2. Koneksi ke Database & Tarik Data Aktual
# ─────────────────────────────────────────────
print("\n[2/4] Menghubungkan ke database MySQL...")
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_DATABASE') or os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASSWORD') if os.getenv('DB_PASSWORD') is not None else os.getenv('DB_PASS', '')
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
    m = re.search(r'(\d{3})(?:\.?(\d+))?', s)
    if not m:
        return "Umum"
    main = int(m.group(1))
    sub = m.group(2) or ""

    if main <= 99:
        if 70 <= main <= 79:
            return "Teknik Grafika & Penerbitan"
        if main <= 9 or (20 <= main <= 29):
            return "Teknik Informatika & Komputer"
        return "Umum"
    elif main <= 199:
        if 150 <= main <= 159:
            return "Psikologi"
        return "Umum"
    elif main <= 299:
        return "Umum"
    elif main <= 399:
        if 330 <= main <= 339:
            if main in (332, 336):
                return "Akuntansi"
            return "Administrasi Niaga"
        elif 380 <= main <= 389:
            return "Administrasi Niaga"
        else:
            return "Umum"
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
            if sub and sub[0] == '3':
                return "Teknik Elektro"
            return "Teknik Mesin"
        elif main <= 623:
            return "Teknik Mesin"
        elif main <= 628:
            return "Teknik Sipil"
        elif main == 629:
            return "Teknik Mesin"
        elif main <= 649:
            return "Umum"
        elif main <= 656:
            return "Administrasi Niaga"
        elif main == 657:
            return "Akuntansi"
        elif main <= 659:
            return "Administrasi Niaga"
        elif main <= 665:
            return "Teknik Mesin"
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
        return "Novel & Sastra"
    else:
        return "Umum"

def clean_ddc(text):
    match = re.search(r'(\d{3})', str(text).strip())
    if match:
        return int(match.group(1))
    return None

def clean_administrative_text(text):
    if not text:
        return ""
    patterns = [
        r'hanya\s+baca\s+di\s+tempat',
        r'tidak\s+bisa\s+dibawa\s+pulang',
        r'buku\s+tidak\s+dapat\s+dipinjam',
        r'buku\s+tidak\s+untuk\s+dipinjamkan',
        r'hanya\s+bisa\s+baca\s+di\s+tempat',
        r'sumbangan\s+dosen',
        r'untuk\s+peminjaman\s+buku\s+dosen\s+hubungi\s+petugas',
        r'buku\s+terbitan\s+pnj\s+press',
        r'buku\s+dosen\s+berada\s+di\s+lantai\s+ii',
        r'pnj\s+corner',
        r'sumbangan\s+dari'
    ]
    combined_pattern = re.compile('|'.join(patterns), re.IGNORECASE)
    cleaned = combined_pattern.sub('', str(text))
    cleaned = re.sub(r'\s+', ' ', cleaned).strip()
    return cleaned

df['ddc_bersih'] = df['kode_ddc'].apply(clean_ddc)
df = df.dropna(subset=['ddc_bersih'])
df['ddc_bersih'] = df['ddc_bersih'].astype(int)
df = df[(df['ddc_bersih'] >= 0) & (df['ddc_bersih'] <= 999)]
df['jurusan_ddc'] = df['kode_ddc'].apply(ddc_to_jurusan)

def combine_text(row):
    parts = []
    judul = str(row.get('judul_buku', '')).strip()
    if judul and judul.lower() not in ('', 'null', 'none', 'nan'):
        parts.append(judul)
    for col in ['deskripsi', 'catatan']:
        val = str(row.get(col, '')).strip()
        if val and val.lower() not in ('', 'null', 'none', 'nan'):
            cleaned_val = clean_administrative_text(val)
            if cleaned_val:
                parts.append(cleaned_val)
    return ' '.join(parts) if parts else ''

df['teks_gabungan'] = df.apply(combine_text, axis=1)
df = df[df['teks_gabungan'].str.len() > 3]

# Reset index
df = df.reset_index(drop=True)

# ─────────────────────────────────────────────
# 3. Reproduksi Test Set yang Sama (random_state=42)
# ─────────────────────────────────────────────
print("\n[3/4] Memisahkan Test Set (random_state=42, sama dengan training)...")

random_state = model_data.get('test_split_random_state', 42)

y_all_ddc = df['jurusan_ddc'].values
train_idx, test_idx = train_test_split(
    np.arange(len(df)), test_size=0.2,
    stratify=y_all_ddc, random_state=random_state
)

df_test = df.iloc[test_idx].copy().reset_index(drop=True)
X_test_text = df_test['teks_gabungan'].values
y_true = df_test['jurusan_ddc'].values  # Label DDC ASLI

print(f"    -> Total data       : {len(df)} buku")
print(f"    -> Data Training    : {len(train_idx)} buku")
print(f"    -> Data Test        : {len(test_idx)} buku")

# ─────────────────────────────────────────────
# 4. Prediksi & Evaluasi Model pada Test Set
# ─────────────────────────────────────────────
print("\n[4/4] Melakukan evaluasi model pada Test Set (label DDC asli)...")

X_test_tfidf = tfidf.transform(X_test_text)
y_pred = clf.predict(X_test_tfidf)

# Metrics
acc = accuracy_score(y_true, y_pred)
precision_mac, recall_mac, f1_mac, _ = precision_recall_fscore_support(y_true, y_pred, average='macro')
precision_wei, recall_wei, f1_wei, _ = precision_recall_fscore_support(y_true, y_pred, average='weighted')

print(f"    -> Selesai mengevaluasi {len(y_true)} data test.")

# ─────────────────────────────────────────────
# Tampilkan Laporan Evaluasi
# ─────────────────────────────────────────────
print("\n" + "=" * 65)
print("  RINGKASAN METRIK EVALUASI MODEL AI")
print("  (Hold-out Test Set - Label DDC Asli)")
print("=" * 65)
print(f"\n  Total Data       : {len(df)} buku")
print(f"  Data Training    : {len(train_idx)} buku ({len(train_idx)/len(df)*100:.1f}%)")
print(f"  Data Test        : {len(test_idx)} buku ({len(test_idx)/len(df)*100:.1f}%)")

print("\n" + "-" * 55)
print(f"{'Metrik':<30} | {'Skor (%)':>15}")
print("-" * 55)
print(f"{'Akurasi (Test Set)':<30} | {acc*100:13.2f}%")
print(f"{'Precision (Macro)':<30} | {precision_mac*100:13.2f}%")
print(f"{'Recall (Macro)':<30} | {recall_mac*100:13.2f}%")
print(f"{'F1-Score (Macro)':<30} | {f1_mac*100:13.2f}%")
print(f"{'Precision (Weighted)':<30} | {precision_wei*100:13.2f}%")
print(f"{'Recall (Weighted)':<30} | {recall_wei*100:13.2f}%")
print(f"{'F1-Score (Weighted)':<30} | {f1_wei*100:13.2f}%")
print("-" * 55)

# Bandingkan dengan metrik yang disimpan saat training (jika ada)
if has_test_metrics:
    saved_acc = model_data.get('accuracy_test', 0) * 100
    print(f"\n  [Verifikasi] Akurasi test dari training: {saved_acc:.2f}%")
    print(f"  [Verifikasi] Akurasi test saat ini     : {acc*100:.2f}%")
    if abs(saved_acc - acc*100) < 0.01:
        print("  [OK] Metrik konsisten - test set berhasil direproduksi.")
    else:
        print("  [WARNING] Ada perbedaan kecil - kemungkinan data DB berubah sejak training.")

print("\n" + "="*65)
print("  LAPORAN KLASIFIKASI MENDETAIL (Classification Report)")
print("  Dievaluasi pada Test Set dengan Label DDC Asli")
print("="*65)
report_str = classification_report(y_true, y_pred)
print(report_str)

# Simpan laporan evaluasi ke file
report_path = os.path.join(MODEL_DIR, 'eval_report.txt')
with open(report_path, 'w', encoding='utf-8') as f:
    f.write("=" * 65 + "\n")
    f.write("  E-DDC - Laporan Evaluasi Performa Model AI\n")
    f.write("  (Evaluasi pada Hold-out Test Set - Label DDC Asli)\n")
    f.write("=" * 65 + "\n\n")
    f.write(f"Total Data Keseluruhan : {len(df)} buku\n")
    f.write(f"Data Training          : {len(train_idx)} buku ({len(train_idx)/len(df)*100:.1f}%)\n")
    f.write(f"Data Test (Hold-out)   : {len(test_idx)} buku ({len(test_idx)/len(df)*100:.1f}%)\n\n")
    f.write(f"--- Metrik Evaluasi pada TEST SET (Label DDC Asli) ---\n")
    f.write(f"Akurasi Test           : {acc*100:.2f}%\n")
    f.write(f"Precision (Macro)      : {precision_mac*100:.2f}%\n")
    f.write(f"Recall (Macro)         : {recall_mac*100:.2f}%\n")
    f.write(f"F1-Score (Macro)       : {f1_mac*100:.2f}%\n")
    f.write(f"Precision (Weighted)   : {precision_wei*100:.2f}%\n")
    f.write(f"Recall (Weighted)      : {recall_wei*100:.2f}%\n")
    f.write(f"F1-Score (Weighted)    : {f1_wei*100:.2f}%\n\n")
    f.write("=== Detail per Jurusan (Test Set - Classification Report) ===\n")
    f.write(report_str)

print(f"\n[OK] Laporan lengkap disimpan di: {report_path}")
print("=" * 65)

import pandas as pd
import numpy as np
from sqlalchemy import create_engine
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import SGDClassifier
from sklearn.model_selection import cross_val_score, StratifiedKFold, train_test_split
from sklearn.metrics import classification_report, accuracy_score, f1_score
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
print("  E-DDC - Training Model Hybrid (2-Pass Label Cleaning)")
print("  (Text Classifier Only)")
print("  Target: 7 Jurusan PNJ + Umum")
print("  Evaluasi: Proper Train/Test Split (80/20)")
print("=" * 65)

# ─────────────────────────────────────────────
# Mapping DDC -> Jurusan PNJ
# ─────────────────────────────────────────────
JURUSAN_LIST = [
    "Teknik Informatika & Komputer",
    "Teknik Sipil",
    "Teknik Mesin",
    "Teknik Elektro",
    "Teknik Grafika & Penerbitan",
    "Administrasi Niaga",
    "Akuntansi",
    "Matematika",
    "Sains",
    "Umum",
]

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
        return "Umum"
    else:
        return "Umum"


# ─────────────────────────────────────────────
# 1. Koneksi ke Database
# ─────────────────────────────────────────────
print("\n[1/11] Menghubungkan ke database MySQL (opac)...")
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
# 2. Bersihkan Data DDC & Map ke Jurusan PNJ
# ─────────────────────────────────────────────
print("\n[2/11] Membersihkan kode DDC & mapping awal ke jurusan PNJ...")

def clean_ddc(text):
    match = re.search(r'(\d{3})', str(text).strip())
    if match:
        return int(match.group(1))
    return None

df['ddc_bersih'] = df['kode_ddc'].apply(clean_ddc)
df = df.dropna(subset=['ddc_bersih'])
df['ddc_bersih'] = df['ddc_bersih'].astype(int)
df = df[(df['ddc_bersih'] >= 0) & (df['ddc_bersih'] <= 999)]
df['jurusan_ddc'] = df['kode_ddc'].apply(ddc_to_jurusan)

print(f"    -> Data bersih: {len(df)} baris.")

# ─────────────────────────────────────────────
# 3. Gabungkan Fitur Teks
# ─────────────────────────────────────────────
print("\n[3/11] Menggabungkan fitur teks (judul + deskripsi + catatan)...")

def clean_administrative_text(text):
    if not text:
        return ""
    # List of regex patterns for administrative phrases (case-insensitive)
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
    # Compile into a single regex pattern
    combined_pattern = re.compile('|'.join(patterns), re.IGNORECASE)
    # Remove the patterns
    cleaned = combined_pattern.sub('', str(text))
    # Clean up excess spaces
    cleaned = re.sub(r'\s+', ' ', cleaned).strip()
    return cleaned

def combine_text(row):
    parts = []
    # Bersihkan judul
    judul = str(row.get('judul_buku', '')).strip()
    if judul and judul.lower() not in ('', 'null', 'none', 'nan'):
        parts.append(judul)
        
    # Bersihkan deskripsi dan catatan dengan filter administratif
    for col in ['deskripsi', 'catatan']:
        val = str(row.get(col, '')).strip()
        if val and val.lower() not in ('', 'null', 'none', 'nan'):
            cleaned_val = clean_administrative_text(val)
            if cleaned_val:
                parts.append(cleaned_val)
    return ' '.join(parts) if parts else ''

df['teks_gabungan'] = df.apply(combine_text, axis=1)
df = df[df['teks_gabungan'].str.len() > 3]

n_with_desc = df['deskripsi'].apply(
    lambda x: str(x).strip().lower() not in ('', 'null', 'none', 'nan', '<NA>')
).sum()
print(f"    -> Total data siap latih: {len(df)} buku")
print(f"    -> Buku dengan deskripsi : {n_with_desc} buku")

# ─────────────────────────────────────────────
# 4. Distribusi Awal (dari DDC)
# ─────────────────────────────────────────────
print("\n[4/11] Distribusi awal (berdasarkan DDC):")
print(f"    {'Jurusan PNJ':<40} {'Jumlah':>8}")
print(f"    {'-'*40} {'-'*8}")
for jur in JURUSAN_LIST:
    jumlah = len(df[df['jurusan_ddc'] == jur])
    print(f"    {jur:<40} {jumlah:>8}")

# ─────────────────────────────────────────────
# 5. Split Data: 80% Training, 20% Test (Hold-out)
# ─────────────────────────────────────────────
print("\n[5/11] Memisahkan data: 80% Training, 20% Test (Hold-out)...")

# Reset index agar tidak ada masalah indexing
df = df.reset_index(drop=True)

X_all_text = df['teks_gabungan'].values
y_all_ddc = df['jurusan_ddc'].values

train_idx, test_idx = train_test_split(
    np.arange(len(df)), test_size=0.2,
    stratify=y_all_ddc, random_state=42
)

df_train = df.iloc[train_idx].copy().reset_index(drop=True)
df_test = df.iloc[test_idx].copy().reset_index(drop=True)

X_train_text = df_train['teks_gabungan'].values
X_test_text = df_test['teks_gabungan'].values
y_train_ddc = df_train['jurusan_ddc'].values
y_test_ddc = df_test['jurusan_ddc'].values

print(f"    -> Data Training : {len(df_train)} buku ({len(df_train)/len(df)*100:.1f}%)")
print(f"    -> Data Test     : {len(df_test)} buku ({len(df_test)/len(df)*100:.1f}%)")
print(f"    -> Test set TIDAK akan disentuh selama training & label cleaning.")

# ─────────────────────────────────────────────
# 6. TF-IDF Vectorization (fit pada Training saja)
# ─────────────────────────────────────────────
print("\n[6/11] TF-IDF Vectorization (fit pada data training)...")

tfidf_eval = TfidfVectorizer(
    max_features=15000,
    ngram_range=(1, 2),
    sublinear_tf=True,
    min_df=2,
    strip_accents='unicode',
    token_pattern=r'(?u)\b\w\w+\b'
)

X_train_tfidf = tfidf_eval.fit_transform(X_train_text)
X_test_tfidf = tfidf_eval.transform(X_test_text)

print(f"    -> Fitur TF-IDF (dari training): {X_train_tfidf.shape[1]} dimensi")

# ─────────────────────────────────────────────
# 7. PASS 1: Latih model awal dari label DDC (Training set saja)
# ─────────────────────────────────────────────
print("\n[7/11] PASS 1 - Melatih model awal dari label DDC (training set)...")

clf_pass1 = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)
clf_pass1.fit(X_train_tfidf, y_train_ddc)

# Prediksi berdasarkan TEKS pada data TRAINING saja
y_train_pred_text = clf_pass1.predict(X_train_tfidf)
y_train_proba = clf_pass1.predict_proba(X_train_tfidf)
y_train_conf = np.max(y_train_proba, axis=1)  # Confidence tertinggi

print(f"    -> Model Pass 1 selesai dilatih pada {len(y_train_ddc)} data training.")

# ─────────────────────────────────────────────
# 8. LABEL CLEANING: Koreksi DDC yang salah (Training set saja)
# ─────────────────────────────────────────────
print("\n[8/11] LABEL CLEANING - Mendeteksi & memperbaiki DDC (training set saja)...")

# Logika: Jika prediksi teks BERBEDA dari label DDC,
# DAN model YAKIN (confidence >= 75%), maka label DDC kemungkinan SALAH.
# Ganti label dengan prediksi teks (yang belajar dari mayoritas data benar).
CONFIDENCE_THRESHOLD = 0.75

disagreements = y_train_pred_text != y_train_ddc
high_conf = y_train_conf >= CONFIDENCE_THRESHOLD
correctable = disagreements & high_conf

n_disagree = disagreements.sum()
n_corrected = correctable.sum()

print(f"    -> Total data training      : {len(y_train_ddc)}")
print(f"    -> Prediksi teks != DDC     : {n_disagree} buku")
print(f"    -> Dikoreksi (conf >= {CONFIDENCE_THRESHOLD*100:.0f}%) : {n_corrected} buku")
print(f"    -> Data test TIDAK dikoreksi: {len(y_test_ddc)} buku (label DDC asli tetap)")

# Buat label final untuk training: gunakan prediksi teks jika dikoreksi, otherwise DDC
y_train_final = y_train_ddc.copy()
y_train_final[correctable] = y_train_pred_text[correctable]

# Tampilkan contoh koreksi
if n_corrected > 0:
    corrected_idx = np.where(correctable)[0]
    print(f"\n    --- Contoh buku yang dikoreksi (maks 10): ---")
    for idx in corrected_idx[:10]:
        row = df_train.iloc[idx]
        conf = y_train_conf[idx] * 100
        print(f"    [{row['kode_ddc']}] \"{row['judul_buku'][:55]}...\"")
        print(f"       DDC -> {y_train_ddc[idx]}  |  Teks -> {y_train_pred_text[idx]} ({conf:.0f}%)")

# ─────────────────────────────────────────────
# 9. PASS 2: Cross-Validation pada training set (label bersih)
# ─────────────────────────────────────────────
print(f"\n[9/11] PASS 2 - Cross-Validation pada training set ({n_corrected} label terkoreksi)...")

clf_eval = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)

# Cross-validation pada data training bersih
cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
scores = cross_val_score(clf_eval, X_train_tfidf, y_train_final, cv=cv, scoring='accuracy')
f1_cv_macro_scores = cross_val_score(clf_eval, X_train_tfidf, y_train_final, cv=cv, scoring='f1_macro')
f1_cv_weighted_scores = cross_val_score(clf_eval, X_train_tfidf, y_train_final, cv=cv, scoring='f1_weighted')

print(f"    -> Akurasi CV (Train)     : {scores.mean()*100:.2f}% (+/- {scores.std()*100:.2f}%)")
print(f"    -> F1 CV Macro (Train)    : {f1_cv_macro_scores.mean()*100:.2f}% (+/- {f1_cv_macro_scores.std()*100:.2f}%)")
print(f"    -> F1 CV Weighted (Train) : {f1_cv_weighted_scores.mean()*100:.2f}% (+/- {f1_cv_weighted_scores.std()*100:.2f}%)")

# Latih model evaluasi pada seluruh training set (label bersih)
clf_eval.fit(X_train_tfidf, y_train_final)

# ─────────────────────────────────────────────
# 10. EVALUASI pada Test Set (label DDC ASLI)
# ─────────────────────────────────────────────
print("\n" + "=" * 65)
print("  [10/11] EVALUASI PADA TEST SET (Hold-out, Label DDC Asli)")
print("  Data test ini TIDAK pernah dilihat model saat training.")
print("  Label DDC asli TIDAK dikoreksi oleh model.")
print("=" * 65)

y_test_pred = clf_eval.predict(X_test_tfidf)

test_acc = accuracy_score(y_test_ddc, y_test_pred)
test_f1_macro = f1_score(y_test_ddc, y_test_pred, average='macro')
test_f1_weighted = f1_score(y_test_ddc, y_test_pred, average='weighted')

print(f"\n    Akurasi Test Set     : {test_acc*100:.2f}%")
print(f"    F1-Score Test Macro  : {test_f1_macro*100:.2f}%")
print(f"    F1-Score Test Weighted: {test_f1_weighted*100:.2f}%")

test_report = classification_report(y_test_ddc, y_test_pred)
print(f"\n    === Classification Report (Test Set - Label DDC Asli) ===")
print(test_report)

# ─────────────────────────────────────────────
# 11. PRODUKSI: Retrain pada SELURUH data untuk model final
# ─────────────────────────────────────────────
print("[11/11] Melatih model PRODUKSI pada seluruh data...")

# Re-fit TF-IDF pada seluruh data (agar model produksi punya vocab lebih luas)
tfidf_prod = TfidfVectorizer(
    max_features=15000,
    ngram_range=(1, 2),
    sublinear_tf=True,
    min_df=2,
    strip_accents='unicode',
    token_pattern=r'(?u)\b\w\w+\b'
)
X_all_tfidf = tfidf_prod.fit_transform(df['teks_gabungan'])

# Pass 1 pada seluruh data untuk cleaning produksi
clf_prod_pass1 = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)
clf_prod_pass1.fit(X_all_tfidf, y_all_ddc)
y_all_pred = clf_prod_pass1.predict(X_all_tfidf)
y_all_proba = clf_prod_pass1.predict_proba(X_all_tfidf)
y_all_conf = np.max(y_all_proba, axis=1)

# Clean labels pada seluruh data untuk produksi
y_all_final = y_all_ddc.copy()
all_correctable = (y_all_pred != y_all_ddc) & (y_all_conf >= CONFIDENCE_THRESHOLD)
y_all_final[all_correctable] = y_all_pred[all_correctable]
n_corrected_all = all_correctable.sum()

# Train model produksi final pada seluruh data bersih
clf_prod = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)
clf_prod.fit(X_all_tfidf, y_all_final)

print(f"    -> Model produksi dilatih pada {len(df)} data (seluruh dataset)")
print(f"    -> Label dikoreksi (produksi): {n_corrected_all} buku")
print(f"    -> PENTING: Metrik yang dilaporkan tetap dari TEST SET (evaluasi valid)")

# Distribusi final (seluruh data)
print(f"\n    Distribusi final per Jurusan PNJ (seluruh data):")
print(f"    {'Jurusan PNJ':<40} {'Jumlah':>8} {'Persen':>8}")
print(f"    {'-'*40} {'-'*8} {'-'*8}")
for jur in JURUSAN_LIST:
    jumlah = (y_all_final == jur).sum()
    persen = (jumlah / len(y_all_final) * 100)
    bar = "#" * int(persen / 2)
    print(f"    {jur:<40} {jumlah:>8} {persen:>7.1f}%  {bar}")
print(f"    {'TOTAL':<40} {len(y_all_final):>8} {'100.0':>7}%")

# ─────────────────────────────────────────────
# Simpan Model Produksi + Metrik Evaluasi
# ─────────────────────────────────────────────
print("\nMenyimpan model produksi + metrik evaluasi...")

save_dir = os.path.dirname(__file__)

hybrid_model = {
    # Model produksi (dilatih pada seluruh data)
    'tfidf': tfidf_prod,
    'clf': clf_prod,
    'jurusan_list': JURUSAN_LIST,
    # === Metrik evaluasi VALID dari test set ===
    'accuracy_test': float(test_acc),
    'f1_test_macro': float(test_f1_macro),
    'f1_test_weighted': float(test_f1_weighted),
    'test_classification_report': test_report,
    # Metrik CV dari training set (untuk referensi)
    'accuracy_cv': float(scores.mean()),
    'f1_cv_macro': float(f1_cv_macro_scores.mean()),
    'f1_cv_weighted': float(f1_cv_weighted_scores.mean()),
    # Info data
    'n_data': len(df),
    'n_train': len(df_train),
    'n_test': len(df_test),
    'n_corrected_train': int(n_corrected),
    'n_corrected_all': int(n_corrected_all),
    'test_split_random_state': 42,
}

hybrid_path = os.path.join(save_dir, 'MODEL_HYBRID.pickle')
with open(hybrid_path, 'wb') as f:
    pickle.dump(hybrid_model, f)
print(f"    -> MODEL_HYBRID.pickle disimpan ({os.path.getsize(hybrid_path)/1024/1024:.1f} MB)")

# Simpan laporan evaluasi ke file
report_path = os.path.join(save_dir, 'eval_report.txt')
with open(report_path, 'w', encoding='utf-8') as f:
    f.write("=" * 65 + "\n")
    f.write("  E-DDC - Laporan Evaluasi Performa Model AI\n")
    f.write("  (Evaluasi pada Hold-out Test Set - Label DDC Asli)\n")
    f.write("=" * 65 + "\n\n")
    f.write(f"Total Data Keseluruhan : {len(df)} buku\n")
    f.write(f"Data Training          : {len(df_train)} buku ({len(df_train)/len(df)*100:.1f}%)\n")
    f.write(f"Data Test (Hold-out)   : {len(df_test)} buku ({len(df_test)/len(df)*100:.1f}%)\n")
    f.write(f"Label Dikoreksi (Train): {n_corrected} buku\n\n")
    f.write(f"--- Metrik Evaluasi pada TEST SET (Label DDC Asli) ---\n")
    f.write(f"Akurasi Test           : {test_acc*100:.2f}%\n")
    f.write(f"F1-Score Test (Macro)  : {test_f1_macro*100:.2f}%\n")
    f.write(f"F1-Score Test (Weighted): {test_f1_weighted*100:.2f}%\n\n")
    f.write(f"--- Metrik Cross-Validation pada TRAINING SET ---\n")
    f.write(f"Akurasi CV (Train)     : {scores.mean()*100:.2f}% (+/- {scores.std()*100:.2f}%)\n")
    f.write(f"F1 CV Macro (Train)    : {f1_cv_macro_scores.mean()*100:.2f}%\n")
    f.write(f"F1 CV Weighted (Train) : {f1_cv_weighted_scores.mean()*100:.2f}%\n\n")
    f.write("=== Detail per Jurusan (Test Set - Classification Report) ===\n")
    f.write(test_report)
print(f"    -> eval_report.txt disimpan.")

# ─────────────────────────────────────────────
# Preview Hasil Prediksi (dari Test Set, pakai model produksi)
# ─────────────────────────────────────────────
print("\n" + "=" * 65)
print("  PREVIEW PREDIKSI JURUSAN PNJ (10 Buku dari Test Set)")
print("=" * 65)

sample = df_test.head(10)
X_sample = tfidf_prod.transform(sample['teks_gabungan'])
probas = clf_prod.predict_proba(X_sample)
classes = clf_prod.classes_

for i in range(len(sample)):
    judul = sample['judul_buku'].iloc[i]
    ddc_real = int(sample['ddc_bersih'].iloc[i])
    jur_ddc = sample['jurusan_ddc'].iloc[i]

    print(f"\nBuku #{i+1}: {judul[:60]}...")
    print(f"   DDC: {ddc_real}  |  Label DDC: {jur_ddc}")

    top_preds = sorted(
        zip(classes, probas[i]), key=lambda x: x[1], reverse=True
    )
    for jur, prob in top_preds[:4]:
        persen = prob * 100
        marker = " << BENAR" if jur == jur_ddc else ""
        bar = "#" * int(persen / 3)
        print(f"   {persen:5.1f}%  {bar}  {jur}{marker}")

# Ringkasan Akhir
print("\n" + "=" * 65)
print("  TRAINING SELESAI ")
print(f"  Total data              : {len(df)} buku")
print(f"  Data Training           : {len(df_train)} buku")
print(f"  Data Test (Hold-out)    : {len(df_test)} buku")
print(f"  Label dikoreksi (train) : {n_corrected} buku")
print(f"  Jumlah jurusan PNJ      : {len(JURUSAN_LIST)} kelas")
print(f"  Fitur TF-IDF            : {X_all_tfidf.shape[1]} dimensi")
print(f"  --- Evaluasi Test Set (METRIK UTAMA) ---")
print(f"  Akurasi Test            : {test_acc*100:.2f}%")
print(f"  F1-Score Test Macro     : {test_f1_macro*100:.2f}%")
print(f"  F1-Score Test Weighted  : {test_f1_weighted*100:.2f}%")
print(f"  --- Cross-Validation (Training Set) ---")
print(f"  Akurasi CV (Train)      : {scores.mean()*100:.2f}%")
print(f"  F1 CV Macro (Train)     : {f1_cv_macro_scores.mean()*100:.2f}%")
print(f"  F1 CV Weighted (Train)  : {f1_cv_weighted_scores.mean()*100:.2f}%")
print("=" * 65)
print("\n[OK] Jalankan 'python api.py' untuk memulai server.")
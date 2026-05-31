import pandas as pd
import numpy as np
from sqlalchemy import create_engine
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import SGDClassifier
from sklearn.model_selection import cross_val_score, StratifiedKFold
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
print("\n[1/9] Menghubungkan ke database MySQL (opac)...")
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
print("\n[2/9] Membersihkan kode DDC & mapping awal ke jurusan PNJ...")

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
print("\n[3/9] Menggabungkan fitur teks (judul + deskripsi + catatan)...")

def combine_text(row):
    parts = []
    for col in ['judul_buku', 'deskripsi', 'catatan']:
        val = str(row.get(col, '')).strip()
        if val and val.lower() not in ('', 'null', 'none', 'nan'):
            parts.append(val)
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
print("\n[4/9] Distribusi awal (berdasarkan DDC):")
print(f"    {'Jurusan PNJ':<40} {'Jumlah':>8}")
print(f"    {'-'*40} {'-'*8}")
for jur in JURUSAN_LIST:
    jumlah = len(df[df['jurusan_ddc'] == jur])
    print(f"    {jur:<40} {jumlah:>8}")

# ─────────────────────────────────────────────
# 5. PASS 1: Latih model awal dari label DDC
# ─────────────────────────────────────────────
print("\n[5/9] PASS 1 - Melatih model awal dari label DDC...")

tfidf = TfidfVectorizer(
    max_features=15000,
    ngram_range=(1, 2),
    sublinear_tf=True,
    min_df=2,
    strip_accents='unicode',
    token_pattern=r'(?u)\b\w\w+\b'
)

X_tfidf = tfidf.fit_transform(df['teks_gabungan'])
y_ddc = df['jurusan_ddc'].values

clf_pass1 = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)
clf_pass1.fit(X_tfidf, y_ddc)

# Prediksi berdasarkan TEKS pada seluruh data training
y_pred_text = clf_pass1.predict(X_tfidf)
y_proba = clf_pass1.predict_proba(X_tfidf)
y_conf = np.max(y_proba, axis=1)  # Confidence tertinggi

print(f"    -> Model Pass 1 selesai dilatih.")

# ─────────────────────────────────────────────
# 6. LABEL CLEANING: Koreksi DDC yang salah
# ─────────────────────────────────────────────
print("\n[6/9] LABEL CLEANING - Mendeteksi & memperbaiki DDC yang salah...")

# Logika: Jika prediksi teks BERBEDA dari label DDC,
# DAN model YAKIN (confidence >= 80%), maka label DDC kemungkinan SALAH.
# Ganti label dengan prediksi teks (yang belajar dari mayoritas data benar).
CONFIDENCE_THRESHOLD = 0.75

disagreements = y_pred_text != y_ddc
high_conf = y_conf >= CONFIDENCE_THRESHOLD
correctable = disagreements & high_conf

n_disagree = disagreements.sum()
n_corrected = correctable.sum()

print(f"    -> Total data              : {len(df)}")
print(f"    -> Prediksi teks != DDC    : {n_disagree} buku")
print(f"    -> Dikoreksi (conf >= {CONFIDENCE_THRESHOLD*100:.0f}%) : {n_corrected} buku")

# Buat label final: gunakan prediksi teks jika dikoreksi, otherwise DDC
df['jurusan_final'] = y_ddc.copy()
df.loc[df.index[correctable], 'jurusan_final'] = y_pred_text[correctable]

# Tampilkan contoh koreksi
if n_corrected > 0:
    corrected_idx = np.where(correctable)[0]
    print(f"\n    --- Contoh buku yang dikoreksi (maks 10): ---")
    for idx in corrected_idx[:10]:
        row = df.iloc[idx]
        conf = y_conf[idx] * 100
        print(f"    [{row['kode_ddc']}] \"{row['judul_buku'][:55]}...\"")
        print(f"       DDC -> {y_ddc[idx]}  |  Teks -> {y_pred_text[idx]} ({conf:.0f}%)")

# ─────────────────────────────────────────────
# 7. PASS 2: Latih ulang dengan label yang sudah bersih
# ─────────────────────────────────────────────
print(f"\n[7/9] PASS 2 - Melatih ulang dengan {n_corrected} label terkoreksi...")

y_final = df['jurusan_final'].values

clf = SGDClassifier(
    loss='modified_huber', alpha=1e-4, max_iter=1000,
    random_state=42, class_weight='balanced', n_jobs=-1
)

# Cross-validation pada data bersih
cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
scores = cross_val_score(clf, X_tfidf, y_final, cv=cv, scoring='accuracy')
f1_cv_macro_scores = cross_val_score(clf, X_tfidf, y_final, cv=cv, scoring='f1_macro')
f1_cv_weighted_scores = cross_val_score(clf, X_tfidf, y_final, cv=cv, scoring='f1_weighted')

print(f"    -> Akurasi CV (Pass 2): {scores.mean()*100:.2f}% (+/- {scores.std()*100:.2f}%)")
print(f"    -> F1-Score CV Macro  : {f1_cv_macro_scores.mean()*100:.2f}% (+/- {f1_cv_macro_scores.std()*100:.2f}%)")
print(f"    -> F1-Score CV Weighted: {f1_cv_weighted_scores.mean()*100:.2f}% (+/- {f1_cv_weighted_scores.std()*100:.2f}%)")

clf.fit(X_tfidf, y_final)
y_pred_final = clf.predict(X_tfidf)
train_acc = accuracy_score(y_final, y_pred_final)
train_f1_macro = f1_score(y_final, y_pred_final, average='macro')
train_f1_weighted = f1_score(y_final, y_pred_final, average='weighted')

print(f"    -> Akurasi Training   : {train_acc*100:.2f}%")
print(f"    -> F1-Score Train Macro : {train_f1_macro*100:.2f}%")
print(f"    -> F1-Score Train Weight: {train_f1_weighted*100:.2f}%")

print("\n    === Classification Report (Pass 2 - Label Bersih) ===")
print(classification_report(y_final, y_pred_final))

# Distribusi final
print("    Distribusi final per Jurusan PNJ:")
print(f"    {'Jurusan PNJ':<40} {'Jumlah':>8} {'Persen':>8}")
print(f"    {'-'*40} {'-'*8} {'-'*8}")
for jur in JURUSAN_LIST:
    jumlah = (y_final == jur).sum()
    persen = (jumlah / len(y_final) * 100)
    bar = "#" * int(persen / 2)
    print(f"    {jur:<40} {jumlah:>8} {persen:>7.1f}%  {bar}")
print(f"    {'TOTAL':<40} {len(y_final):>8} {'100.0':>7}%")

# ─────────────────────────────────────────────
# 8. Simpan Model
# ─────────────────────────────────────────────
print("\n[8/9] Menyimpan model...")

save_dir = os.path.dirname(__file__)

hybrid_model = {
    'tfidf': tfidf,
    'clf': clf,
    'jurusan_list': JURUSAN_LIST,
    'accuracy_cv': float(scores.mean()),
    'accuracy_train': float(train_acc),
    'f1_cv_macro': float(f1_cv_macro_scores.mean()),
    'f1_cv_weighted': float(f1_cv_weighted_scores.mean()),
    'f1_train_macro': float(train_f1_macro),
    'f1_train_weighted': float(train_f1_weighted),
    'n_data': len(df),
    'n_corrected': int(n_corrected),
}

hybrid_path = os.path.join(save_dir, 'MODEL_HYBRID.pickle')
with open(hybrid_path, 'wb') as f:
    pickle.dump(hybrid_model, f)
print(f"    -> MODEL_HYBRID.pickle disimpan ({os.path.getsize(hybrid_path)/1024/1024:.1f} MB)")

# ─────────────────────────────────────────────
# Preview Hasil Prediksi
# ─────────────────────────────────────────────
print("\n" + "=" * 65)
print("  PREVIEW PREDIKSI JURUSAN PNJ (10 Buku)")
print("=" * 65)

sample = df.head(10)
X_sample = tfidf.transform(sample['teks_gabungan'])
probas = clf.predict_proba(X_sample)
classes = clf.classes_

for i in range(len(sample)):
    judul = sample['judul_buku'].iloc[i]
    ddc_real = int(sample['ddc_bersih'].iloc[i])
    jur_final = sample['jurusan_final'].iloc[i]

    print(f"\nBuku #{i+1}: {judul[:60]}...")
    print(f"   DDC: {ddc_real}  |  Label: {jur_final}")

    top_preds = sorted(
        zip(classes, probas[i]), key=lambda x: x[1], reverse=True
    )
    for jur, prob in top_preds[:4]:
        persen = prob * 100
        marker = " << BENAR" if jur == jur_final else ""
        bar = "#" * int(persen / 3)
        print(f"   {persen:5.1f}%  {bar}  {jur}{marker}")

# Ringkasan
print("\n" + "=" * 65)
print("  TRAINING SELESAI! (2-Pass Label Cleaning)")
print(f"  Total data terlatih     : {len(df)} buku")
print(f"  Label dikoreksi         : {n_corrected} buku")
print(f"  Jumlah jurusan PNJ      : {len(JURUSAN_LIST)} kelas")
print(f"  Fitur TF-IDF            : {X_tfidf.shape[1]} dimensi")
print(f"  Akurasi CV (Pass 2)     : {scores.mean()*100:.2f}%")
print(f"  F1-Score CV Macro       : {f1_cv_macro_scores.mean()*100:.2f}%")
print(f"  F1-Score CV Weighted    : {f1_cv_weighted_scores.mean()*100:.2f}%")
print(f"  Akurasi Training        : {train_acc*100:.2f}%")
print(f"  F1-Score Train Macro    : {train_f1_macro*100:.2f}%")
print(f"  F1-Score Train Weighted : {train_f1_weighted*100:.2f}%")
print("=" * 65)
print("\n[OK] Jalankan 'python api.py' untuk memulai server.")
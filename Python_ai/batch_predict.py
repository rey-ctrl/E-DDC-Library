"""
E-DDC Batch Prediction Script
==============================
Script ini dijalankan SEKALI (offline) untuk:
1. Menambahkan kolom predicted_jurusan & predicted_confidence ke tabel biblio
2. Memprediksi seluruh buku menggunakan model AI (MODEL_HYBRID.pickle)
3. Menyimpan hasil prediksi ke database

Jalankan ulang kapan saja setelah model diperbarui (retrain).

Usage:
    python batch_predict.py
"""

import numpy as np
from sqlalchemy import create_engine, text
import pickle
import re
import os
import time
import json
import warnings
warnings.filterwarnings('ignore')

try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass

print("=" * 65)
print("  E-DDC - Batch Prediction (Pre-computed Classification)")
print("  Menyimpan hasil prediksi AI ke database untuk performa optimal")
print("=" * 65)

# ─────────────────────────────────────────────
# 1. Koneksi Database
# ─────────────────────────────────────────────
print("\n[1/6] Menghubungkan ke database MySQL...")
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine  = create_engine(DB_URL)

# Test koneksi
try:
    with engine.connect() as conn:
        conn.execute(text("SELECT 1"))
    print("    -> Koneksi berhasil.")
except Exception as e:
    print(f"[ERROR] Gagal terhubung ke database: {e}")
    exit(1)

# ─────────────────────────────────────────────
# 2. Buat Kolom Baru (Jika Belum Ada)
# ─────────────────────────────────────────────
print("\n[2/6] Memeriksa & membuat kolom prediksi di tabel biblio...")

with engine.connect() as conn:
    # Cek apakah kolom sudah ada
    check_query = text("""
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :db_name 
          AND TABLE_NAME = 'biblio' 
          AND COLUMN_NAME = 'predicted_jurusan'
    """)
    col_exists = conn.execute(check_query, {"db_name": DB_NAME}).scalar()

    if col_exists == 0:
        print("    -> Menambahkan kolom predicted_jurusan (VARCHAR 100)...")
        conn.execute(text(
            "ALTER TABLE biblio ADD COLUMN predicted_jurusan VARCHAR(100) DEFAULT NULL"
        ))
        conn.commit()
        print("    -> Kolom predicted_jurusan berhasil ditambahkan.")
    else:
        print("    -> Kolom predicted_jurusan sudah ada.")

    # Cek kolom confidence
    check_conf = text("""
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :db_name 
          AND TABLE_NAME = 'biblio' 
          AND COLUMN_NAME = 'predicted_confidence'
    """)
    conf_exists = conn.execute(check_conf, {"db_name": DB_NAME}).scalar()

    if conf_exists == 0:
        print("    -> Menambahkan kolom predicted_confidence (FLOAT)...")
        conn.execute(text(
            "ALTER TABLE biblio ADD COLUMN predicted_confidence FLOAT DEFAULT NULL"
        ))
        conn.commit()
        print("    -> Kolom predicted_confidence berhasil ditambahkan.")
    else:
        print("    -> Kolom predicted_confidence sudah ada.")

    # Cek kolom predicted_multilabel (JSON/TEXT untuk menyimpan semua label)
    check_ml = text("""
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :db_name 
          AND TABLE_NAME = 'biblio' 
          AND COLUMN_NAME = 'predicted_multilabel'
    """)
    ml_exists = conn.execute(check_ml, {"db_name": DB_NAME}).scalar()

    if ml_exists == 0:
        print("    -> Menambahkan kolom predicted_multilabel (TEXT)...")
        conn.execute(text(
            "ALTER TABLE biblio ADD COLUMN predicted_multilabel TEXT DEFAULT NULL"
        ))
        conn.commit()
        print("    -> Kolom predicted_multilabel berhasil ditambahkan.")
    else:
        print("    -> Kolom predicted_multilabel sudah ada.")

    # Tambahkan INDEX pada kolom predicted_jurusan agar filter SQL cepat
    check_idx = text("""
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = :db_name
          AND TABLE_NAME = 'biblio'
          AND INDEX_NAME = 'idx_predicted_jurusan'
    """)
    idx_exists = conn.execute(check_idx, {"db_name": DB_NAME}).scalar()

    if idx_exists == 0:
        print("    -> Menambahkan INDEX pada predicted_jurusan...")
        conn.execute(text(
            "ALTER TABLE biblio ADD INDEX idx_predicted_jurusan (predicted_jurusan)"
        ))
        conn.commit()
        print("    -> INDEX berhasil ditambahkan.")
    else:
        print("    -> INDEX predicted_jurusan sudah ada.")


# ─────────────────────────────────────────────
# 3. Muat Model AI
# ─────────────────────────────────────────────
print("\n[3/6] Memuat model AI (MODEL_HYBRID.pickle)...")
MODEL_DIR = os.path.dirname(__file__)
HYBRID_PATH = os.path.join(MODEL_DIR, 'MODEL_HYBRID.pickle')

if not os.path.exists(HYBRID_PATH):
    print(f"[ERROR] Model tidak ditemukan di: {HYBRID_PATH}")
    print("Harap jalankan training terlebih dahulu: python train_model.py")
    exit(1)

with open(HYBRID_PATH, 'rb') as f:
    model_data = pickle.load(f)

tfidf_model = model_data.get('tfidf')
clf_model = model_data.get('clf')
JURUSAN_LIST = model_data.get('jurusan_list', [])

print(f"    -> Model berhasil dimuat.")
print(f"    -> Kelas Jurusan: {len(JURUSAN_LIST)}")

# ─────────────────────────────────────────────
# Helper Functions (sama dengan api.py & train_model.py)
# ─────────────────────────────────────────────
def clean_administrative_text(text_val):
    if not text_val:
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
    cleaned = combined_pattern.sub('', str(text_val))
    cleaned = re.sub(r'\s+', ' ', cleaned).strip()
    return cleaned

def build_text(title=None, description=None, notes=None):
    parts = []
    if title:
        s = str(title).strip()
        if s and s.lower() not in ('null', 'none', 'nan', ''):
            parts.append(s)
    for val in [description, notes]:
        if val:
            s = str(val).strip()
            if s and s.lower() not in ('null', 'none', 'nan', ''):
                cleaned_val = clean_administrative_text(s)
                if cleaned_val:
                    parts.append(cleaned_val)
    return ' '.join(parts) if parts else ''

KEYWORD_HINTS = {
    "Teknik Informatika & Komputer": [
        "pemrograman", "programming", "algoritma", "database", "jaringan",
        "komputer", "computer", "software", "hardware", "coding", "java",
        "python", "php", "html", "css", "javascript", "web", "internet",
        "basis data", "data mining", "big data", "data science",
        "sistem informasi", "kecerdasan buatan",
        "artificial intelligence", "machine learning", "linux", "android",
    ],
    "Teknik Sipil": [
        "beton", "konstruksi", "bangunan", "jembatan", "jalan raya",
        "struktur", "pondasi", "tanah", "geoteknik", "hidrologi",
        "irigasi", "drainase", "perkerasan", "arsitektur", "tata ruang",
    ],
    "Teknik Mesin": [
        "mesin", "engine", "turbin", "pompa", "pneumatik", "hidrolik",
        "termodinamika", "manufaktur", "pengelasan", "welding", "cnc",
        "otomotif", "motor", "refrigerasi", "hvac", "perancangan mesin",
    ],
    "Teknik Elektro": [
        "elektronika", "rangkaian", "listrik", "electrical", "circuit",
        "mikrokontroler", "arduino", "plc", "sensor", "transistor",
        "tegangan", "arus", "daya listrik", "transformator", "relay",
        "robotik", "robot", "telekomunikasi", "sinyal", "antena",
    ],
    "Teknik Grafika & Penerbitan": [
        "cetak", "percetakan", "printing", "grafika", "desain grafis",
        "layout", "tipografi", "penerbitan", "publishing", "offset",
        "fotografi", "photography", "illustrasi", "prepress", "jurnalistik",
        "jurnalisme", "wartawan", "pers", "berita", "komunikasi massa",
        "pemotretan", "fotograsi", "photo shoot", "photoshoot", "pemotret", "potret"
    ],
    "Administrasi Niaga": [
        "manajemen", "management", "pemasaran", "marketing", "bisnis",
        "business", "perdagangan", "ekspor", "impor", "logistik",
        "sumber daya manusia", "sdm", "hrm", "organisasi", "kepemimpinan",
        "leadership", "wirausaha", "entrepreneur", "e-commerce",
    ],
    "Akuntansi": [
        "akuntansi", "accounting", "audit", "auditing", "pajak", "tax", "neraca",
        "laporan keuangan", "financial", "anggaran", "budget", "debit",
        "kredit", "jurnal akuntansi", "aset", "liabilitas", "ekuitas",
    ],
    "Matematika": [
        "matematika", "mathematics", "kalkulus", "calculus", "aljabar",
        "algebra", "statistik", "statistics", "probabilitas", "logika",
        "diskrit", "numerik", "geometri", "trigonometri", "matriks",
    ],
    "Sains": [
        "sains", "science", "biologi", "biology", "kimia", "chemistry",
        "fisika", "physics", "astronomi", "alam", "ekologi", "lingkungan",
    ],
    "Umum": [
        "agama", "islam", "shalat", "quran", "alkitab", "filsafat",
        "psikologi", "bahasa", "sastra", "novel", "puisi", "cerpen",
        "sejarah", "geografi", "terapi", "herbal", "penyakit", "obat",
        "kesehatan", "health", "kedokteran", "medical", "farmasi",
        "gizi", "nutrisi", "diet", "diabetes", "kanker", "jantung",
        "stroke", "darah tinggi", "kolesterol", "pendidikan", "olahraga",
    ],
}

def keyword_boost(text_val, labels):
    if not labels or not text_val:
        return labels
    top_conf = labels[0]["probabilitas"]
    if top_conf >= 60.0:
        return labels
    text_lower = text_val.lower()
    keyword_scores = {}
    for jurusan, keywords in KEYWORD_HINTS.items():
        score = sum(1 for kw in keywords if kw in text_lower)
        if score > 0:
            keyword_scores[jurusan] = score
    if not keyword_scores:
        return labels
    best_jurusan = max(keyword_scores, key=keyword_scores.get)
    boosted = []
    total_boost = 15.0 * keyword_scores[best_jurusan]
    for item in labels:
        new_item = item.copy()
        if item["label"] == best_jurusan:
            new_item["probabilitas"] = min(99.0, item["probabilitas"] + total_boost)
        else:
            reduction = total_boost / max(len(labels) - 1, 1)
            new_item["probabilitas"] = max(0.0, item["probabilitas"] - reduction)
        boosted.append(new_item)
    total_prob = sum(b["probabilitas"] for b in boosted)
    if total_prob > 0:
        for b in boosted:
            b["probabilitas"] = round(b["probabilitas"] / total_prob * 100, 2)
    boosted.sort(key=lambda x: x["probabilitas"], reverse=True)
    return boosted


def apply_iot_rules(text, labels):
    if not text or not labels:
        return labels

    iot_keywords = {
        "iot", "internet of things", "arduino", "raspberry pi", "esp32", 
        "esp8266", "mikrokontroler", "microcontroller", "servo", 
        "instrumentasi", "sensor", "aktuator", "actuator", "automation", 
        "otomasi", "wemos", "node-red", "mqtt"
    }

    text_lower = text.lower()
    has_iot = any(kw in text_lower for kw in iot_keywords)

    if has_iot:
        it_label = "Teknik Informatika & Komputer"
        found = False
        for l in labels:
            if l["label"] == it_label:
                found = True
                if l["probabilitas"] < 20.0:
                    l["probabilitas"] = 20.0
                break

        if not found:
            labels.append({
                "label": it_label,
                "probabilitas": 20.0,
                "metode": "iot_rule"
            })

        # Re-normalisasi agar total = 100%
        total_prob = sum(l["probabilitas"] for l in labels)
        if total_prob > 0:
            for l in labels:
                l["probabilitas"] = round(l["probabilitas"] / total_prob * 100, 2)
            # Re-sort
            labels.sort(key=lambda x: x["probabilitas"], reverse=True)

    return labels


def apply_exclusion_rules(labels):
    if not labels:
        return labels

    teknik_labels = {
        "Teknik Informatika & Komputer",
        "Teknik Sipil",
        "Teknik Mesin",
        "Teknik Elektro",
        "Teknik Grafika & Penerbitan"
    }

    has_teknik = any(l["label"] in teknik_labels for l in labels)
    has_niaga = any(l["label"] == "Administrasi Niaga" for l in labels)

    if has_teknik and has_niaga:
        # Find the max probability for Teknik and Administrasi Niaga
        max_teknik_prob = max(l["probabilitas"] for l in labels if l["label"] in teknik_labels)
        niaga_prob = max(l["probabilitas"] for l in labels if l["label"] == "Administrasi Niaga")

        if max_teknik_prob > niaga_prob:
            # Keep Teknik, remove Administrasi Niaga
            labels = [l for l in labels if l["label"] != "Administrasi Niaga"]
        else:
            # Keep Administrasi Niaga, remove all Teknik
            labels = [l for l in labels if l["label"] not in teknik_labels]

        # Re-normalize remaining probabilities to sum to 100%
        total_prob = sum(l["probabilitas"] for l in labels)
        if total_prob > 0:
            for l in labels:
                l["probabilitas"] = round(l["probabilitas"] / total_prob * 100, 2)
            # Re-sort after re-normalization
            labels.sort(key=lambda x: x["probabilitas"], reverse=True)

    return labels


def predict_single(book_text, threshold=0.15):
    """Prediksi jurusan untuk satu buku. Return (jurusan, confidence, multilabel_list)."""
    default_label = [{"label": "Umum", "probabilitas": 0.0, "metode": "default"}]
    if not book_text or tfidf_model is None or clf_model is None:
        return ("Umum", 0.0, default_label)

    try:
        X = tfidf_model.transform([book_text])
        probas = clf_model.predict_proba(X)[0]
        classes = clf_model.classes_

        labels = []
        for jur, prob in zip(classes, probas):
            if prob >= threshold:
                labels.append({
                    "label": jur,
                    "probabilitas": round(float(prob) * 100, 2),
                    "metode": "text_classifier"
                })
        labels.sort(key=lambda x: x["probabilitas"], reverse=True)

        # Keyword boost
        labels = keyword_boost(book_text, labels)

        # Terapkan aturan IoT
        labels = apply_iot_rules(book_text, labels)

        # Terapkan eksklusi
        labels = apply_exclusion_rules(labels)

        # Saring kembali jika ada label di bawah 15% setelah boost/eksklusi
        labels = [l for l in labels if l["probabilitas"] >= 15.0]

        if labels:
            return (labels[0]["label"], labels[0]["probabilitas"], labels)
        else:
            return ("Umum", 0.0, default_label)
    except Exception as e:
        print(f"    [WARN] Prediksi error: {e}")
        return ("Umum", 0.0, default_label)


# ─────────────────────────────────────────────
# 4. Tarik Semua Buku dari Database
# ─────────────────────────────────────────────
print("\n[4/6] Menarik seluruh data buku dari database...")

with engine.connect() as conn:
    query = text("""
        SELECT 
            biblio_id,
            title,
            classification,
            spec_detail_info,
            notes
        FROM biblio
        WHERE classification IS NOT NULL
          AND classification != ''
          AND classification != 'NONE'
          AND opac_hide = 0
    """)
    rows = conn.execute(query).mappings().all()

total_buku = len(rows)
print(f"    -> Total buku yang akan diprediksi: {total_buku}")

if total_buku == 0:
    print("[WARNING] Tidak ada buku yang ditemukan. Selesai.")
    exit(0)

# ─────────────────────────────────────────────
# 5. Prediksi Batch & Update Database
# ─────────────────────────────────────────────
print(f"\n[5/6] Menjalankan prediksi AI untuk {total_buku} buku...")
print("       (Proses ini mungkin memakan waktu beberapa menit)")

start_time = time.time()
batch_size = 100  # Update per batch untuk efisiensi
updates = []
jurusan_counter = {}

for i, row in enumerate(rows):
    book_text = build_text(
        title=row["title"],
        description=row.get("spec_detail_info"),
        notes=row.get("notes")
    )

    jurusan, confidence, multilabel = predict_single(book_text)

    updates.append({
        "bid": row["biblio_id"],
        "jur": jurusan,
        "conf": round(confidence, 2),
        "ml": json.dumps(multilabel, ensure_ascii=False)
    })

    # Hitung distribusi
    jurusan_counter[jurusan] = jurusan_counter.get(jurusan, 0) + 1

    # Progress indicator
    if (i + 1) % 200 == 0 or (i + 1) == total_buku:
        elapsed = time.time() - start_time
        rate = (i + 1) / elapsed if elapsed > 0 else 0
        remaining = (total_buku - (i + 1)) / rate if rate > 0 else 0
        print(f"    -> {i + 1}/{total_buku} buku diprediksi "
              f"({(i+1)/total_buku*100:.1f}%) "
              f"[{elapsed:.1f}s elapsed, ~{remaining:.0f}s remaining]")

    # Batch update ke database setiap batch_size
    if len(updates) >= batch_size:
        with engine.connect() as conn:
            for u in updates:
                conn.execute(
                    text("UPDATE biblio SET predicted_jurusan = :jur, predicted_confidence = :conf, predicted_multilabel = :ml WHERE biblio_id = :bid"),
                    u
                )
            conn.commit()
        updates = []

# Flush sisa updates
if updates:
    with engine.connect() as conn:
        for u in updates:
            conn.execute(
                text("UPDATE biblio SET predicted_jurusan = :jur, predicted_confidence = :conf, predicted_multilabel = :ml WHERE biblio_id = :bid"),
                u
            )
        conn.commit()

elapsed_total = time.time() - start_time
print(f"\n    -> Selesai! Total waktu: {elapsed_total:.1f} detik")
print(f"    -> Rata-rata: {total_buku/elapsed_total:.0f} buku/detik")

# ─────────────────────────────────────────────
# 6. Statistik & Verifikasi
# ─────────────────────────────────────────────
print("\n[6/6] Verifikasi & Statistik Prediksi...")

with engine.connect() as conn:
    # Verifikasi jumlah yang terisi
    verify = conn.execute(text(
        "SELECT COUNT(*) FROM biblio WHERE predicted_jurusan IS NOT NULL AND predicted_jurusan != ''"
    )).scalar()
    
    # Verifikasi jumlah NULL
    verify_null = conn.execute(text(
        "SELECT COUNT(*) FROM biblio WHERE (predicted_jurusan IS NULL OR predicted_jurusan = '') AND opac_hide = 0 AND classification IS NOT NULL AND classification != ''"
    )).scalar()

print(f"\n    Buku dengan prediksi: {verify}")
print(f"    Buku tanpa prediksi : {verify_null}")

print(f"\n    {'Jurusan PNJ':<40} {'Jumlah':>8} {'Persen':>8}")
print(f"    {'-'*40} {'-'*8} {'-'*8}")

sorted_jurusan = sorted(jurusan_counter.items(), key=lambda x: x[1], reverse=True)
for jur, count in sorted_jurusan:
    persen = count / total_buku * 100
    bar = "#" * int(persen / 2)
    print(f"    {jur:<40} {count:>8} {persen:>7.1f}%  {bar}")

print(f"    {'TOTAL':<40} {total_buku:>8} {'100.0':>7}%")

print("\n" + "=" * 65)
print("  BATCH PREDICTION SELESAI!")
print(f"  {total_buku} buku berhasil diprediksi dan disimpan ke database.")
print("  Kolom: predicted_jurusan, predicted_confidence")
print("")
print("  Sekarang jalankan 'python api.py' untuk memulai server")
print("  yang sudah teroptimasi.")
print("=" * 65)

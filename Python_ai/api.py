from flask import Flask, jsonify, request
from flask_cors import CORS
from sqlalchemy import create_engine, text
import numpy as np
import pickle
import re
import os
import json

# Load .env jika ada
try:
    from dotenv import load_dotenv
    # Load parent .env (Laravel root) first, then local .env if any
    load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'), override=True)
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'), override=True)
except ImportError:
    pass

app = Flask(__name__)
CORS(app)

# ─────────────────────────────────────────────
# Konfigurasi Database
# ─────────────────────────────────────────────
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_DATABASE') or os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASSWORD') if os.getenv('DB_PASSWORD') is not None else os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine = create_engine(DB_URL)

# ─────────────────────────────────────────────
# Daftar Jurusan PNJ
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
    "Novel & Sastra",
    "Psikologi",
    "Umum",
]

# DDC Mapping Multilabel
def ddc_to_jurusan(kode_ddc_raw):
    s = str(kode_ddc_raw).strip()
    m = re.search(r'(\d{1,3})(?:\.?(\d+))?', s)
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
            return "Teknik Mesin"  # 666-669: Metalurgi, keramik, kaca -> Teknik Mesin
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

# ─────────────────────────────────────────────
# Load Model Hybrid
# ─────────────────────────────────────────────
MODEL_DIR = os.path.dirname(__file__)
HYBRID_PATH = os.path.join(MODEL_DIR, 'MODEL_HYBRID.pickle')

tfidf_model = None
clf_model = None
model_info = {}

def load_models():
    global tfidf_model, clf_model, model_info

    if os.path.exists(HYBRID_PATH):
        with open(HYBRID_PATH, 'rb') as f:
            data = pickle.load(f)
        tfidf_model = data.get('tfidf')
        clf_model = data.get('clf')
        model_info = {
            'mode': 'hybrid',
            'accuracy_cv': data.get('accuracy_cv', 0),
            'accuracy_test': data.get('accuracy_test', data.get('accuracy_train', 0)),
            'f1_cv_macro': data.get('f1_cv_macro', 0),
            'f1_cv_weighted': data.get('f1_cv_weighted', 0),
            'f1_test_macro': data.get('f1_test_macro', data.get('f1_train_macro', 0)),
            'f1_test_weighted': data.get('f1_test_weighted', data.get('f1_train_weighted', 0)),
            'n_data': data.get('n_data', 0),
            'n_train': data.get('n_train', 0),
            'n_test': data.get('n_test', 0),
            'jurusan_list': data.get('jurusan_list', JURUSAN_LIST),
        }
        print("[OK] Model HYBRID dimuat (Text Classifier)")
        return True

    print("[WARNING] Tidak ada model yang ditemukan!")
    return False

load_models()

# ─────────────────────────────────────────────
# Auto-migration: Pastikan kolom predicted_multilabel ada
# ─────────────────────────────────────────────
def ensure_multilabel_column():
    """Buat kolom predicted_multilabel jika belum ada, lalu isi yang masih NULL."""
    try:
        with engine.connect() as conn:
            # Cek apakah kolom sudah ada
            check = conn.execute(text("""
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'biblio' 
                AND COLUMN_NAME = 'predicted_multilabel'
            """), {"db": DB_NAME}).scalar()

            if check == 0:
                conn.execute(text(
                    "ALTER TABLE biblio ADD COLUMN predicted_multilabel TEXT DEFAULT NULL"
                ))
                conn.commit()
                print("[OK] Kolom predicted_multilabel berhasil ditambahkan.")
            else:
                print("[OK] Kolom predicted_multilabel sudah ada.")

            # Hitung buku yang belum punya multilabel
            null_count = conn.execute(text("""
                SELECT COUNT(*) FROM biblio 
                WHERE predicted_multilabel IS NULL 
                  AND predicted_jurusan IS NOT NULL 
                  AND opac_hide = 0
            """)).scalar()

            if null_count > 0 and tfidf_model is not None and clf_model is not None:
                print(f"[INFO] Mengisi predicted_multilabel untuk {null_count} buku...")
                rows = conn.execute(text("""
                    SELECT biblio_id, title, spec_detail_info, notes, classification
                    FROM biblio
                    WHERE predicted_multilabel IS NULL
                      AND predicted_jurusan IS NOT NULL
                      AND opac_hide = 0
                """)).mappings().all()

                batch = []
                for i, row in enumerate(rows):
                    book_text = build_text(
                        title=row["title"],
                        description=row.get("spec_detail_info"),
                        notes=row.get("notes")
                    )
                    multilabel = predict_multilabel(
                        ddc_value=clean_ddc(row["classification"]),
                        book_text=book_text,
                        ddc_raw=row["classification"]
                    )
                    if not multilabel:
                        jur = ddc_to_jurusan(row["classification"])
                        multilabel = [{"label": jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]

                    batch.append({
                        "bid": row["biblio_id"],
                        "ml": json.dumps(multilabel, ensure_ascii=False)
                    })

                    if len(batch) >= 100:
                        for u in batch:
                            conn.execute(text(
                                "UPDATE biblio SET predicted_multilabel = :ml WHERE biblio_id = :bid"
                            ), u)
                        conn.commit()
                        batch = []

                if batch:
                    for u in batch:
                        conn.execute(text(
                            "UPDATE biblio SET predicted_multilabel = :ml WHERE biblio_id = :bid"
                        ), u)
                    conn.commit()

                print(f"[OK] {len(rows)} buku berhasil diisi predicted_multilabel.")
            elif null_count > 0:
                print(f"[WARNING] {null_count} buku belum punya multilabel, tapi model belum dimuat.")
            else:
                print("[OK] Semua buku sudah punya predicted_multilabel.")
    except Exception as e:
        print(f"[WARNING] Auto-migration predicted_multilabel: {e}")

# ─────────────────────────────────────────────
# Helper: Bersihkan kode DDC
# ─────────────────────────────────────────────
def clean_ddc(text_val):
    if text_val is None:
        return None
    match = re.search(r'(\d{1,3})', str(text_val).strip())
    if match:
        return int(match.group(1))
    return None

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

# ─────────────────────────────────────────────
# Keyword heuristic 
# ─────────────────────────────────────────────
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
        # Tambahan kuat
        "taguchi", "robust design", "quality engineering", "perpindahan panas",
        "heat transfer", "fluid mechanics", "mekanika fluida", "thermodynamics",
        "kinetika", "kinetics", "tribologi", "tribology", "bahan bakar",
        "combustion", "pembakaran", "pengecoran", "casting", "machining",
        "permesinan", "teknik industri", "industrial engineering",
    ],
    "Teknik Elektro": [
        "elektronika", "rangkaian", "listrik", "electrical", "circuit",
        "mikrokontroler", "arduino", "plc", "sensor", "transistor",
        "tegangan", "arus", "daya listrik", "transformator", "relay",
        "robotik", "robot", "telekomunikasi", "sinyal", "antena",
        # Tambahan kuat
        "instrumentasi", "instrumentation", "kontrol", "control system",
        "sistem kontrol", "otomasi", "automation", "dsp", "fpga",
        "power electronics", "elektronika daya", "motor listrik",
    ],
    "Teknik Grafika & Penerbitan": [
        "cetak", "percetakan", "printing", "grafika", "desain grafis",
        "layout", "tipografi", "penerbitan", "publishing", "offset",
        "fotografi", "photography", "illustrasi", "prepress", "jurnalistik",
        "jurnalisme", "wartawan", "pers", "berita", "komunikasi massa",
        "pemotretan", "fotograsi", "photo shoot", "photoshoot", "pemotret", "potret",
        "media massa", "redaksi", "editing naskah", "kemasan", "jilid", "penjilidan", "tinta"
    ],
    "Administrasi Niaga": [
        "manajemen", "management", "pemasaran", "marketing", "bisnis",
        "business", "perdagangan", "ekspor", "impor", "logistik",
        "sumber daya manusia", "sdm", "hrm", "organisasi", "kepemimpinan",
        "leadership", "wirausaha", "entrepreneur", "e-commerce",
        # Tambahan kuat
        "supply chain", "rantai pasok", "inventory", "procurement",
        "operasional", "operations management", "project management",
        "manajemen proyek", "strategi bisnis", "business strategy",
    ],
    "Akuntansi": [
        "akuntansi", "accounting", "audit", "auditing", "pajak", "tax", "neraca",
        "laporan keuangan", "financial statement", "anggaran", "budget", "debit",
        "kredit", "jurnal akuntansi", "aset", "liabilitas", "ekuitas",
        # Tambahan kuat
        "general ledger", "buku besar", "piutang", "hutang", "receivable",
        "payable", "depreciation", "depresiasi", "amortisasi", "leasing",
        "konsolidasi", "consolidation", "ifrs", "gaap", "revenue recognition",
        "cost accounting", "akuntansi biaya", "managerial accounting",
        "akuntansi manajerial", "pembukuan", "bookkeeping", "intermediate accounting",
    ],
    "Matematika": [
        "matematika", "mathematics", "kalkulus", "calculus", "aljabar",
        "algebra", "statistik", "statistics", "probabilitas", "logika",
        "diskrit", "numerik", "geometri", "trigonometri", "matriks",
        # Tambahan kuat
        "diferensial", "integral", "persamaan diferensial", "differential equation",
        "analisis numerik", "numerical analysis", "riset operasi", "operations research",
        "optimasi", "optimization", "graph theory", "teori graf",
    ],
    "Sains": [
        "sains", "science", "biologi", "biology", "kimia", "chemistry",
        "fisika", "physics", "astronomi", "alam", "ekologi", "lingkungan",
        "laboratorium", "eksperimen", "praktikum", "sel", "gen", "molekul",
        "tumbuhan", "hewan", "tata surya", "bumi", "antariksa",
        # Tambahan kuat
        "mikrobiologi", "microbiology", "biokimia", "biochemistry",
        "genetika", "genetics", "farmakologi", "pharmacology",
        "geologi", "geology", "mineralogi", "botani", "zoologi",
    ],
    "Novel & Sastra": [
        "sastra", "novel", "puisi", "cerpen", "prosa", "fiksi",
        "kesusastraan", "pantun", "hikayat", "dongeng", "cerita"
    ],
    "Psikologi": [
        "psikologi", "psychology", "mental", "jiwa", "kepribadian",
        "terapi", "konseling", "perilaku", "psikolog", "emosi",
        "kognitif", "perilaku manusia", "gangguan jiwa", "perkembangan anak",
        "interaksi sosial", "kecemasan", "depresi", "stres", "psikoanalisis"
    ],
    "Umum": [
        "agama", "islam", "shalat", "quran", "alkitab", "filsafat",
        "bahasa", "sejarah", "geografi", "herbal", "penyakit", "obat",
        "kesehatan", "health", "kedokteran", "medical", "farmasi",
        "gizi", "nutrisi", "diet", "diabetes", "kanker", "jantung",
        "stroke", "darah tinggi", "kolesterol", "pendidikan", "olahraga",
    ],
}

# Keyword sangat spesifik yang SELALU override label utama (terlepas dari confidence)
# Dipakai untuk memperbaiki buku yang DDC-nya salah di katalog perpustakaan
STRONG_TITLE_OVERRIDES = {
    "Akuntansi": [
        r'\baccounting\b', r'\bakuntansi\b', r'\baudit(?:ing)?\b',
        r'\btax\s+accounting\b', r'\bintermediate\s+accounting\b',
        r'\bfinancial\s+accounting\b', r'\bcost\s+accounting\b',
        r'\bmanagerial\s+accounting\b', r'\bperpajakan\b',
        r'\blaporan\s+keuangan\b', r'\bgeneral\s+ledger\b',
        r'\bbuku\s+besar\b', r'\bifrs\b', r'\bgaap\b',
    ],
    "Teknik Mesin": [
        r'\btaguchi\b', r'\bheat\s+transfer\b', r'\bperpindahan\s+panas\b',
        r'\btermodinamika\b', r'\bthermodynamics\b',
        r'\bmechanical\s+engineering\b', r'\bteknik\s+mesin\b',
        r'\bfluid\s+mechanics\b', r'\bmekanika\s+fluida\b',
        r'\bmanufacturing\b', r'\bpengelasan\b', r'\bwelding\b',
        r'\bcnc\b', r'\bmachining\b', r'\bpermesinan\b',
        r'\brobust\s+design\b', r'\bquality\s+engineering\b',
        # Tambahan metalurgi
        r'\blogam\b', r'\bmetalurgi\b', r'\bmetallurgy\b',
        r'\bpengerjaan\s+logam\b', r'\bmetal\s+work\b',
        r'\bpengecoran\b', r'\bcasting\b', r'\bfoundry\b',
        r'\bkorosi\b', r'\bcorrosion\b',
    ],
    "Teknik Informatika & Komputer": [
        r'\bpemrograman\b', r'\bprogramming\b', r'\balgorithm\b',
        r'\bdata\s+structures?\b', r'\bdatabase\b',
        r'\bmachine\s+learning\b', r'\bdeep\s+learning\b',
        r'\bartificial\s+intelligence\b', r'\bkecerdasan\s+buatan\b',
        r'\bsoftware\s+engineering\b', r'\brekayasa\s+perangkat\s+lunak\b',
    ],
    "Teknik Sipil": [
        r'\bfundamentals?\s+of\s+(?:soil|structural|hydraulic)\b',
        r'\bgeoteknik\b', r'\bgeotechnical\b',
        r'\bhidrologi\b', r'\bhydrology\b',
        r'\bperkerasan\s+jalan\b', r'\bpavement\b',
        r'\bstruktur\s+beton\b', r'\breinforced\s+concrete\b',
    ],
    "Teknik Elektro": [
        r'\binstrumentation\b', r'\binstrumentasi\b',
        r'\bpower\s+electronics\b', r'\belektronika\s+daya\b',
        r'\bcontrol\s+system\b', r'\bsistem\s+kontrol\b',
        r'\btelekomunikasi\b', r'\btelecommunications?\b',
        r'\bmikrokontroler\b', r'\bmicrocontroller\b',
    ],
    "Matematika": [
        r'\bcalculus\b', r'\bkalkulus\b',
        r'\bdifferential\s+equations?\b', r'\bpersamaan\s+diferensial\b',
        r'\bnumerical\s+analysis\b', r'\banalisis\s+numerik\b',
        r'\blinear\s+algebra\b', r'\baljabar\s+linear\b',
        r'\boperations?\s+research\b', r'\briset\s+operasi\b',
    ],
    "Sains": [
        r'\bmicrobiology\b', r'\bmikrobiologi\b',
        r'\bbiochemistry\b', r'\bbiokimia\b',
        r'\bphysics\b', r'\bfisika\b',
        r'\borganic\s+chemistry\b', r'\bkimia\s+organik\b',
        r'\bgenetics\b', r'\bgenetika\b',
        r'\bphysical\s+chemistry\b',
    ],
}


def keyword_boost(text, labels):
  
    if not labels or not text:
        return labels

    top_conf = labels[0]["probabilitas"]
    if top_conf >= 60.0:
        return labels  # Model sudah cukup yakin

    text_lower = text.lower()

    # Hitung skor keyword untuk setiap jurusan
    keyword_scores = {}
    for jurusan, keywords in KEYWORD_HINTS.items():
        score = sum(1 for kw in keywords if kw in text_lower)
        if score > 0:
            keyword_scores[jurusan] = score

    if not keyword_scores:
        return labels  # Tidak ada keyword cocok

    # Jurusan dengan keyword paling banyak cocok
    best_jurusan = max(keyword_scores, key=keyword_scores.get)

    # --- PENCEGAHAN OVER-BOOSTING (CONTEXT-AWARE BYPASS) ---
    # Jika jurusan terbaik terdeteksi sebagai IT karena kata kunci umum seperti 'komputer', 
    # tetapi ada kata kunci Akuntansi atau Administrasi Niaga, batalkan boost IT.
    if best_jurusan == "Teknik Informatika & Komputer":
        other_specialties = ["Akuntansi", "Administrasi Niaga", "Matematika", "Teknik Sipil", "Teknik Mesin"]
        has_other = any(spec in keyword_scores for spec in other_specialties)
        if has_other:
            # Cari jurusan alternatif non-IT yang memiliki kata kunci terbanyak
            alternatives = {k: v for k, v in keyword_scores.items() if k != "Teknik Informatika & Komputer"}
            if alternatives:
                best_jurusan = max(alternatives, key=alternatives.get)
            else:
                return labels # Batalkan boost sepenuhnya jika ada konflik kontekstual

    # Boost: naikkan probabilitas jurusan yang cocok keyword
    boosted = []
    boost_pct = float(os.getenv('BOOST_PERCENTAGE', '5.0'))
    total_boost = boost_pct * keyword_scores[best_jurusan]  

    for item in labels:
        new_item = item.copy()
        if item["label"] == best_jurusan:
            new_item["probabilitas"] = min(99.0, item["probabilitas"] + total_boost)
        else:
            # Kurangi proporsional
            reduction = total_boost / max(len(labels) - 1, 1)
            new_item["probabilitas"] = max(0.0, item["probabilitas"] - reduction)
        boosted.append(new_item)

    # Normalisasi agar total = 100%
    total_prob = sum(b["probabilitas"] for b in boosted)
    if total_prob > 0:
        for b in boosted:
            b["probabilitas"] = round(b["probabilitas"] / total_prob * 100, 2)

    # Re-sort
    boosted.sort(key=lambda x: x["probabilitas"], reverse=True)
    return boosted


def apply_title_keyword_override(labels, book_text):
    """
    Override kuat berbasis keyword sangat spesifik di judul/teks.
    Selalu aktif (tidak tergantung confidence) — mengatasi buku
    yang DDC-nya salah di katalog perpustakaan.
    
    Contoh:
    - 'Intermediate Accounting' (DDC 567/Sains) -> override ke Akuntansi
    - 'Taguchi Methods' (DDC 871/Novel) -> override ke Teknik Mesin
    """
    if not labels or not book_text:
        return labels

    text_lower = book_text.lower()
    matched_overrides = {}  # jurusan -> jumlah match

    for jurusan, patterns in STRONG_TITLE_OVERRIDES.items():
        count = sum(1 for pat in patterns if re.search(pat, text_lower, re.IGNORECASE))
        if count > 0:
            matched_overrides[jurusan] = count

    if not matched_overrides:
        return labels

    # Jurusan override dengan match terbanyak
    best_override = max(matched_overrides, key=matched_overrides.get)
    current_top = labels[0]["label"]

    # Hanya override jika berbeda dari top saat ini
    if best_override == current_top:
        return labels

    # Cek apakah override ini reasonable (tidak konflik)
    # Jangan override jika match hanya 1 dan top label sudah punya confidence > 85%
    if matched_overrides[best_override] == 1 and labels[0]["probabilitas"] > 85.0:
        return labels

    # Temukan target jurusan dalam daftar
    target_idx = next(
        (i for i, l in enumerate(labels) if l["label"] == best_override), -1
    )

    if target_idx >= 0:
        # Tukar ke posisi pertama
        promoted = labels.pop(target_idx)
        old_top_prob = labels[0]["probabilitas"]
        # Beri probability yang cukup dominan
        promoted["probabilitas"] = max(
            promoted["probabilitas"], min(80.0, old_top_prob + 15.0)
        )
        labels.insert(0, promoted)
    else:
        # Tambahkan label baru
        labels.insert(0, {
            "label": best_override,
            "probabilitas": 75.0,
            "metode": "title_override"
        })

    # Re-normalisasi
    total = sum(l["probabilitas"] for l in labels)
    if total > 0:
        for l in labels:
            l["probabilitas"] = round(l["probabilitas"] / total * 100, 2)

    labels.sort(key=lambda x: x["probabilitas"], reverse=True)
    labels = [l for l in labels if l["probabilitas"] >= 15.0]
    return labels


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


def apply_ddc_sanity_check(labels, ddc_raw):
    """
    Post-processing berbasis DDC: jika label teratas dari model AI
    secara domain JELAS bertentangan dengan kode DDC buku,
    promosikan label DDC ke posisi pertama.
    
    Contoh kasus yang diperbaiki:
    - 'Perpindahan Panas' (DDC 536 -> Sains, model -> Akuntansi) => koreksi ke Sains
    - 'Taguchi Methods' (DDC 620 -> Teknik Mesin, model -> Novel) => koreksi ke Teknik Mesin
    - 'Intermediate Accounting' (DDC 657 -> Akuntansi, model -> Sains) => koreksi ke Akuntansi
    """
    if not labels or not ddc_raw:
        return labels

    ddc_jurusan = ddc_to_jurusan(ddc_raw)
    if ddc_jurusan == "Umum":
        return labels  # DDC tidak cukup spesifik, jangan koreksi

    top_label = labels[0]["label"]
    if top_label == ddc_jurusan:
        return labels  # Sudah cocok

    # Pasangan domain yang JELAS tidak kompatibel:
    # key = jurusan DDC, value = label-label yang TIDAK boleh jadi top jika DDC mengatakan key
    HARD_INCOMPATIBLE = {
        "Teknik Mesin": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga"
        },
        "Teknik Sipil": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga"
        },
        "Teknik Elektro": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga"
        },
        "Teknik Informatika & Komputer": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga"
        },
        "Teknik Grafika & Penerbitan": {
            "Psikologi", "Akuntansi", "Administrasi Niaga", "Matematika", "Sains"
        },
        "Matematika": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga",
            "Teknik Mesin", "Teknik Sipil"
        },
        "Sains": {
            "Novel & Sastra", "Psikologi", "Akuntansi", "Administrasi Niaga"
        },
        "Akuntansi": {
            "Novel & Sastra", "Psikologi", "Sains",
            "Teknik Mesin", "Teknik Sipil", "Teknik Elektro",
            "Teknik Grafika & Penerbitan"
        },
        "Administrasi Niaga": {
            "Novel & Sastra", "Sains", "Matematika",
            "Teknik Mesin", "Teknik Sipil", "Teknik Elektro"
        },
        "Novel & Sastra": {
            "Teknik Mesin", "Teknik Sipil", "Teknik Elektro",
            "Matematika", "Sains", "Akuntansi"
        },
        "Psikologi": {
            "Teknik Mesin", "Teknik Sipil", "Teknik Elektro",
            "Matematika", "Sains", "Akuntansi"
        },
    }

    if ddc_jurusan not in HARD_INCOMPATIBLE:
        return labels
    if top_label not in HARD_INCOMPATIBLE[ddc_jurusan]:
        return labels

    # Temukan label DDC di dalam daftar prediksi
    ddc_idx = next(
        (i for i, l in enumerate(labels) if l["label"] == ddc_jurusan), -1
    )

    if ddc_idx >= 0:
        # Angkat label DDC ke posisi pertama dan swap dengan top saat ini
        promoted = labels.pop(ddc_idx)
        old_top_prob = labels[0]["probabilitas"]
        # Berikan probabilitas yang masuk akal (setidaknya sama dengan yg lama)
        promoted["probabilitas"] = max(
            promoted["probabilitas"], min(75.0, old_top_prob + 10.0)
        )
        labels.insert(0, promoted)
    else:
        # DDC jurusan tidak ada dalam prediksi, tambahkan sebagai label baru
        labels.insert(0, {
            "label": ddc_jurusan,
            "probabilitas": 70.0,
            "metode": "ddc_correction"
        })

    # Re-normalisasi agar total = 100%
    total = sum(l["probabilitas"] for l in labels)
    if total > 0:
        for l in labels:
            l["probabilitas"] = round(l["probabilitas"] / total * 100, 2)

    # Sort ulang dan filter threshold
    labels.sort(key=lambda x: x["probabilitas"], reverse=True)
    labels = [l for l in labels if l["probabilitas"] >= 15.0]
    return labels


# ─────────────────────────────────────────────
# Prediksi Multilabel -> Jurusan PNJ
# ─────────────────────────────────────────────
def predict_multilabel(ddc_value=None, book_text=None, ddc_raw=None, threshold=0.15):

    labels = []

    # --- Metode 1: Text Classifier (utama) ---
    if book_text and tfidf_model is not None and clf_model is not None:
        try:
            X = tfidf_model.transform([book_text])
            probas = clf_model.predict_proba(X)[0]
            classes = clf_model.classes_

            for jur, prob in zip(classes, probas):
                if prob >= threshold:
                    labels.append({
                        "label": jur,
                        "probabilitas": round(float(prob) * 100, 2),
                        "metode": "text_classifier"
                    })

            labels.sort(key=lambda x: x["probabilitas"], reverse=True)

            # Koreksi dengan keyword jika confidence rendah
            labels = keyword_boost(book_text, labels)

            # Terapkan aturan IoT
            labels = apply_iot_rules(book_text, labels)

            # Terapkan aturan eksklusi (Teknik vs Niaga)
            labels = apply_exclusion_rules(labels)

            # Terapkan sanity check berbasis DDC (koreksi misklasifikasi blatant)
            if ddc_raw:
                labels = apply_ddc_sanity_check(labels, ddc_raw)

            # Override berbasis keyword kuat di judul (mengatasi DDC salah di katalog)
            labels = apply_title_keyword_override(labels, book_text)

            # Saring kembali jika ada label di bawah 15% setelah semua post-processing
            labels = [l for l in labels if l["probabilitas"] >= 15.0]

            return labels
        except Exception as e:
            print(f"[WARN] Text classifier error: {e}")

    # --- Metode 2: DDC Mapping fallback ---
    if ddc_raw or ddc_value is not None:
        jur = ddc_to_jurusan(ddc_raw or str(ddc_value))
        labels.append({
            "label": jur,
            "probabilitas": 100.0,
            "metode": "ddc_mapping"
        })

    return labels

# Jalankan auto-migration setelah semua helper function didefinisikan
ensure_multilabel_column()

# ─────────────────────────────────────────────
# Endpoint: search buku (OPTIMIZED - Pre-computed)
# Menggunakan kolom predicted_jurusan dari database
# alih-alih inferensi ML real-time per buku
# ─────────────────────────────────────────────
@app.route('/api/buku/search', methods=['GET'])
def search_buku():
    keyword = request.args.get('keyword', '').strip()
    filters = request.args.get('filters', '').strip()
    filter_mode = request.args.get('filter_mode', 'or').strip().lower()
    page = int(request.args.get('page', 1))
    per_page = min(int(request.args.get('per_page', 24)), 50)  # Max 50
    mode = request.args.get('mode', 'database').strip().lower()

    filter_list = [f.strip() for f in filters.split(',')] if filters else []

    try:
        with engine.connect() as conn:
            # ── Base query: selalu ambil predicted_jurusan & predicted_confidence ──
            query_base = """
                SELECT 
                    b.biblio_id,
                    b.title,
                    b.sor        AS author,
                    b.publish_year,
                    b.collation,
                    b.classification,
                    b.call_number,
                    b.image,
                    b.spec_detail_info,
                    b.notes,
                    b.predicted_jurusan,
                    b.predicted_confidence,
                    b.predicted_multilabel
                FROM biblio b
                WHERE b.opac_hide = 0 
                  AND b.classification IS NOT NULL 
                  AND b.classification != ''
                  AND b.classification REGEXP '[0-9]'
            """

            count_base = """
                SELECT COUNT(*) FROM biblio b
                WHERE b.opac_hide = 0
                  AND b.classification IS NOT NULL
                  AND b.classification != ''
                  AND b.classification REGEXP '[0-9]'
            """

            params = {}
            where_extra = ""

            # ── Keyword filter ──
            if keyword:
                m_range = re.match(r'^(\d{3})-(\d{3})$', keyword)
                m_ddc = re.match(r'^(\d{3})$', keyword)
                if m_range:
                    start_val = int(m_range.group(1))
                    end_val = int(m_range.group(2))
                    where_extra += f" AND (CAST(SUBSTRING(b.classification, 1, 3) AS UNSIGNED) BETWEEN {start_val} AND {end_val})"
                elif m_ddc:
                    ddc_val = int(m_ddc.group(1))
                    where_extra += f" AND CAST(SUBSTRING(b.classification, 1, 3) AS UNSIGNED) = {ddc_val}"
                else:
                    # Hanya cari berdasarkan judul buku dan nomor DDC (classification)
                    # Notes dan deskripsi (spec_detail_info) TIDAK diikutkan dalam pencarian
                    where_extra += " AND (b.title LIKE :kw OR b.classification LIKE :kw)"
                    params["kw"] = f"%{keyword}%"

            # ── Filter jurusan (SQL-based, menggunakan predicted_multilabel dengan fallback ke predicted_jurusan) ──
            if filter_list:
                conds = []
                for idx, f in enumerate(filter_list):
                    like_key = f"filter_like_{idx}"
                    val_key = f"filter_val_{idx}"
                    params[like_key] = f'%"label": "{f}"%'
                    params[val_key] = f
                    conds.append(f"(b.predicted_multilabel LIKE :{like_key} OR (b.predicted_multilabel IS NULL AND b.predicted_jurusan = :{val_key}))")

                if filter_mode == 'and':
                    # AND: buku harus memiliki SEMUA jurusan yang dipilih
                    where_extra += f" AND ({' AND '.join(conds)})"
                else:
                    # OR: buku cukup memiliki salah satu jurusan
                    where_extra += f" AND ({' OR '.join(conds)})"

            # ── Hitung total ──
            total_query = text(count_base + where_extra)
            total_items = conn.execute(total_query, params).scalar()

            # ── Query data dengan paginasi SQL ──
            offset = (page - 1) * per_page
            if keyword and not re.match(r'^\d{3}-\d{3}$', keyword):
                order_clause = " ORDER BY b.title ASC"
            elif filter_list:
                order_clause = " ORDER BY b.predicted_confidence DESC, b.title ASC"
            else:
                order_clause = " ORDER BY b.title ASC"

            final_query = text(
                query_base + where_extra + order_clause + f" LIMIT {per_page} OFFSET {offset}"
            )
            rows = conn.execute(final_query, params).mappings().all()

        # ── Build response ──
        hasil = []
        matching_count = 0

        for row in rows:
            ddc_bersih = clean_ddc(row["classification"])
            if ddc_bersih is None:
                continue

            actual_jur = ddc_to_jurusan(row["classification"])

            if mode == 'realtime':
                book_text = build_text(
                    title=row["title"],
                    description=row.get("spec_detail_info"),
                    notes=row.get("notes")
                )
                multilabel = predict_multilabel(
                    ddc_value=ddc_bersih,
                    book_text=book_text,
                    ddc_raw=row["classification"]
                )
                if not multilabel:
                    multilabel = [{"label": actual_jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]
                
                predicted_jur = multilabel[0]["label"] if multilabel else "Umum"
            else:
                # Gunakan predicted_multilabel dari database (JSON)
                predicted_ml_raw = row.get("predicted_multilabel")
                if predicted_ml_raw:
                    try:
                        multilabel = json.loads(predicted_ml_raw)
                    except (json.JSONDecodeError, TypeError):
                        # Fallback ke single label jika JSON rusak
                        predicted_jur = row.get("predicted_jurusan") or "Umum"
                        predicted_conf = row.get("predicted_confidence") or 0.0
                        multilabel = [{
                            "label": predicted_jur,
                            "probabilitas": round(float(predicted_conf), 2),
                            "metode": "pre_computed"
                        }]
                else:
                    # Fallback: kolom predicted_multilabel belum terisi
                    predicted_jur = row.get("predicted_jurusan") or "Umum"
                    predicted_conf = row.get("predicted_confidence") or 0.0
                    multilabel = [{
                        "label": predicted_jur,
                        "probabilitas": round(float(predicted_conf), 2),
                        "metode": "pre_computed"
                    }]
                
                predicted_jur = row.get("predicted_jurusan") or (multilabel[0]["label"] if multilabel else "Umum")

            # Hitung apakah prediksi cocok dengan DDC asli (Prototyping)
            if predicted_jur == actual_jur:
                matching_count += 1

            # Bersihkan deskripsi (hapus literal 'null')
            desc_raw = row.get("spec_detail_info") or ""
            desc = desc_raw.strip() if str(desc_raw).strip().lower() not in ('null', 'none', 'nan', '') else ""

            # Bersihkan notes
            notes_raw = row.get("notes") or ""
            notes_clean = notes_raw.strip() if str(notes_raw).strip().lower() not in ('null', 'none', 'nan', '') else ""

            # Bersihkan collation (Pages) menggunakan Regex
            collation_raw = str(row.get("collation") or "-")
            pages_clean = collation_raw
            pages_match = re.search(r'(\d+)\s*(?:hlm|hal|halaman)', collation_raw, re.IGNORECASE)
            if pages_match:
                pages_clean = pages_match.group(1) + " hlm."

            # Bersihkan image (abaikan default cover SLiMS)
            image_val = row['image']
            if image_val and re.search(r'cover\.(jpg|jpeg|png)$', str(image_val).strip().lower()):
                image_val = None

            buku = {
                "biblio_id":    row["biblio_id"],
                "Book_Title":   row["title"],
                "Author":       row["author"] or "Tidak Diketahui",
                "Year_Published": row["publish_year"] or "-",
                "Pages":        pages_clean,
                "Book_Code":    row["classification"] or "-",
                "Call_Number":  row["call_number"] or "-",
                "Publisher":    "-",
                "Image":        f"/repository/{image_val}" if image_val else None,
                "Description":  desc,
                "Notes":        notes_clean,
                "has_notes":    bool(notes_clean),
                "DDC_Bersih":   ddc_bersih,
                "Multilabel":   multilabel,
                "actual_jurusan": actual_jur,
                "predicted_jurusan": predicted_jur,
            }
            hasil.append(buku)

        page_accuracy = round((matching_count / len(hasil)) * 100, 2) if hasil else 0.0

        return jsonify({
            "data": hasil,
            "pagination": {
                "total": total_items,
                "page": page,
                "per_page": per_page,
                "total_pages": max(1, (total_items + per_page - 1) // per_page)
            },
            "stats": {
                "page_matching": matching_count,
                "page_accuracy": page_accuracy,
                "page_total": len(hasil)
            }
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Endpoint: GET /api/buku/detail/<id>
# ─────────────────────────────────────────────
@app.route('/api/buku/detail/<int:biblio_id>', methods=['GET'])
def detail_buku(biblio_id):
    try:
        with engine.connect() as conn:
            query = text("""
                SELECT 
                    b.biblio_id, b.title, b.sor AS author,
                    b.edition, b.isbn_issn, b.publish_year,
                    b.collation, b.series_title, b.classification,
                    b.call_number, b.notes, b.image, b.spec_detail_info
                FROM biblio b
                WHERE b.biblio_id = :id
                LIMIT 1
            """)
            row = conn.execute(query, {"id": biblio_id}).mappings().first()

        if not row:
            return jsonify({"error": "Buku tidak ditemukan."}), 404

        ddc_bersih = clean_ddc(row["classification"])
        book_text = build_text(
            title=row["title"],
            description=row.get("spec_detail_info"),
            notes=row.get("notes")
        )
        multilabel = predict_multilabel(
            ddc_value=ddc_bersih, book_text=book_text,
            ddc_raw=row["classification"]
        )

        actual_jur = ddc_to_jurusan(row["classification"])
        predicted_jur = multilabel[0]["label"] if multilabel else "Umum"

        # Bersihkan deskripsi
        desc_raw = row.get("spec_detail_info") or ""
        desc = desc_raw.strip() if str(desc_raw).strip().lower() not in ('null', 'none', 'nan', '') else ""

        # Bersihkan notes
        notes_raw = row.get("notes") or ""
        notes_clean = notes_raw.strip() if str(notes_raw).strip().lower() not in ('null', 'none', 'nan', '') else ""

        # Bersihkan collation (Pages) menggunakan Regex
        collation_raw = str(row.get("collation") or "-")
        pages_clean = collation_raw
        pages_match = re.search(r'(\d+)\s*(?:hlm|hal|halaman)', collation_raw, re.IGNORECASE)
        if pages_match:
            pages_clean = pages_match.group(1) + " hlm."

        # Bersihkan image (abaikan default cover SLiMS)
        image_val = row['image']
        if image_val and re.search(r'cover\.(jpg|jpeg|png)$', str(image_val).strip().lower()):
            image_val = None

        return jsonify({
            "biblio_id":    row["biblio_id"],
            "Book_Title":   row["title"],
            "Author":       row["author"] or "-",
            "Edition":      row["edition"] or "-",
            "ISBN":         row["isbn_issn"] or "-",
            "Year_Published": row["publish_year"] or "-",
            "Pages":        pages_clean,
            "Series":       row["series_title"] or "-",
            "Book_Code":    row["classification"] or "-",
            "Call_Number":  row["call_number"] or "-",
            "Notes":        notes_clean or "-",
            "has_notes":    bool(notes_clean),
            "Publisher":    "-",
            "Place":        "-",
            "Image":        f"/repository/{image_val}" if image_val else None,
            "Description":  desc,
            "DDC_Bersih":   ddc_bersih,
            "Multilabel":   multilabel,
            "actual_jurusan": actual_jur,
            "predicted_jurusan": predicted_jur,
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500



# ─────────────────────────────────────────────
# Endpoint: POST /api/admin/recompute
# Force re-compute predicted_multilabel untuk SEMUA buku
# menggunakan pipeline AI + DDC sanity check terbaru
# ─────────────────────────────────────────────
@app.route('/api/admin/recompute', methods=['POST'])
def recompute_all():
    """Re-prediksi seluruh database dengan model & aturan terkini."""
    if tfidf_model is None or clf_model is None:
        return jsonify({"error": "Model belum dimuat. Jalankan train_model.py dulu."}), 503

    try:
        with engine.connect() as conn:
            rows = conn.execute(text("""
                SELECT biblio_id, title, spec_detail_info, notes, classification
                FROM biblio
                WHERE opac_hide = 0
                  AND classification IS NOT NULL
                  AND classification != ''
                ORDER BY biblio_id ASC
            """)).mappings().all()

        total = len(rows)
        updated = 0
        errors = 0
        batch = []

        print(f"[RECOMPUTE] Mulai recompute {total} buku...")

        for row in rows:
            try:
                book_text = build_text(
                    title=row["title"],
                    description=row.get("spec_detail_info"),
                    notes=row.get("notes")
                )
                multilabel = predict_multilabel(
                    ddc_value=clean_ddc(row["classification"]),
                    book_text=book_text,
                    ddc_raw=row["classification"]
                )
                if not multilabel:
                    jur = ddc_to_jurusan(row["classification"])
                    multilabel = [{"label": jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]

                batch.append({
                    "bid":  row["biblio_id"],
                    "ml":   json.dumps(multilabel, ensure_ascii=False),
                    "jur":  multilabel[0]["label"],
                    "conf": round(multilabel[0]["probabilitas"], 2)
                })
                updated += 1

            except Exception as e:
                errors += 1
                print(f"[WARN] Error biblio_id={row['biblio_id']}: {e}")

            # Flush ke DB setiap 100 buku
            if len(batch) >= 100:
                with engine.connect() as conn:
                    for u in batch:
                        conn.execute(text("""
                            UPDATE biblio
                            SET predicted_multilabel  = :ml,
                                predicted_jurusan     = :jur,
                                predicted_confidence  = :conf
                            WHERE biblio_id = :bid
                        """), u)
                    conn.commit()
                print(f"[RECOMPUTE] {updated}/{total} diproses...")
                batch = []

        # Flush sisa
        if batch:
            with engine.connect() as conn:
                for u in batch:
                    conn.execute(text("""
                        UPDATE biblio
                        SET predicted_multilabel  = :ml,
                            predicted_jurusan     = :jur,
                            predicted_confidence  = :conf
                        WHERE biblio_id = :bid
                    """), u)
                conn.commit()

        print(f"[RECOMPUTE] Selesai: {updated} diupdate, {errors} error.")
        return jsonify({
            "message": f"Recompute selesai: {updated} buku diupdate, {errors} error.",
            "total":   total,
            "updated": updated,
            "errors":  errors
        }), 200

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Endpoint: POST /api/buku/store
# Menyimpan buku baru + langsung prediksi AI
# ─────────────────────────────────────────────
@app.route('/api/buku/store', methods=['POST'])
def store_buku():
    try:
        data = request.get_json(force=True) if request.is_json else request.form.to_dict()

        title = data.get('title', '').strip()
        sor = data.get('sor', '').strip()
        classification = data.get('classification', '').strip()

        if not title or not classification:
            return jsonify({"error": "Field 'title' dan 'classification' wajib diisi."}), 400

        # Build teks untuk prediksi AI
        book_text = build_text(
            title=title,
            description=data.get('spec_detail_info', ''),
            notes=data.get('notes', '')
        )

        # Prediksi jurusan menggunakan model AI
        predicted_jur = "Umum"
        predicted_conf = 0.0
        multilabel_data = [{"label": "Umum", "probabilitas": 0.0, "metode": "default"}]

        if book_text and tfidf_model is not None and clf_model is not None:
            try:
                multilabel_result = predict_multilabel(
                    ddc_value=clean_ddc(classification),
                    book_text=book_text,
                    ddc_raw=classification
                )
                if multilabel_result:
                    predicted_jur = multilabel_result[0]["label"]
                    predicted_conf = multilabel_result[0]["probabilitas"]
                    multilabel_data = multilabel_result
            except Exception as e:
                print(f"[WARN] Prediksi gagal untuk buku baru: {e}")
                # Fallback ke DDC mapping
                predicted_jur = ddc_to_jurusan(classification)
                predicted_conf = 100.0
                multilabel_data = [{"label": predicted_jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]
        else:
            # Jika model tidak tersedia, gunakan DDC mapping
            predicted_jur = ddc_to_jurusan(classification)
            predicted_conf = 100.0
            multilabel_data = [{"label": predicted_jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]

        # Insert ke database
        with engine.connect() as conn:
            insert_query = text("""
                INSERT INTO biblio (
                    title, sor, classification, publish_year, isbn_issn,
                    call_number, edition, collation, series_title,
                    spec_detail_info, notes, opac_hide,
                    predicted_jurusan, predicted_confidence, predicted_multilabel,
                    last_update
                ) VALUES (
                    :title, :sor, :classification, :publish_year, :isbn_issn,
                    :call_number, :edition, :collation, :series_title,
                    :spec_detail_info, :notes, 0,
                    :predicted_jurusan, :predicted_confidence, :predicted_multilabel,
                    NOW()
                )
            """)

            conn.execute(insert_query, {
                "title": title,
                "sor": sor,
                "classification": classification,
                "publish_year": data.get('publish_year', ''),
                "isbn_issn": data.get('isbn_issn', ''),
                "call_number": data.get('call_number', ''),
                "edition": data.get('edition', ''),
                "collation": data.get('collation', ''),
                "series_title": data.get('series_title', ''),
                "spec_detail_info": data.get('spec_detail_info', ''),
                "notes": data.get('notes', ''),
                "predicted_jurusan": predicted_jur,
                "predicted_confidence": round(predicted_conf, 2),
                "predicted_multilabel": json.dumps(multilabel_data, ensure_ascii=False),
            })
            conn.commit()

        return jsonify({
            "message": f"Buku '{title}' berhasil ditambahkan.",
            "predicted_jurusan": predicted_jur,
            "predicted_confidence": round(predicted_conf, 2),
        }), 201

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Endpoint: PUT /api/buku/update/<int:biblio_id>
# Mengupdate buku + memprediksi ulang AI jika teks berubah
# ─────────────────────────────────────────────
@app.route('/api/buku/update/<int:biblio_id>', methods=['PUT', 'POST'])
def update_buku_api(biblio_id):
    try:
        data = request.get_json(force=True) if request.is_json else request.form.to_dict()

        title = data.get('title', '').strip()
        classification = data.get('classification', '').strip()

        if not title or not classification:
            return jsonify({"error": "Field 'title' dan 'classification' wajib diisi."}), 400

        # Build teks untuk prediksi AI
        book_text = build_text(
            title=title,
            description=data.get('spec_detail_info', ''),
            notes=data.get('notes', '')
        )

        # Prediksi jurusan menggunakan model AI
        predicted_jur = "Umum"
        predicted_conf = 0.0
        multilabel_data = [{"label": "Umum", "probabilitas": 0.0, "metode": "default"}]

        if book_text and tfidf_model is not None and clf_model is not None:
            try:
                multilabel_result = predict_multilabel(
                    ddc_value=clean_ddc(classification),
                    book_text=book_text,
                    ddc_raw=classification
                )
                if multilabel_result:
                    predicted_jur = multilabel_result[0]["label"]
                    predicted_conf = multilabel_result[0]["probabilitas"]
                    multilabel_data = multilabel_result
            except Exception as e:
                print(f"[WARN] Prediksi gagal untuk update buku: {e}")
                predicted_jur = ddc_to_jurusan(classification)
                predicted_conf = 100.0
                multilabel_data = [{"label": predicted_jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]
        else:
            predicted_jur = ddc_to_jurusan(classification)
            predicted_conf = 100.0
            multilabel_data = [{"label": predicted_jur, "probabilitas": 100.0, "metode": "ddc_mapping"}]

        # Update database
        with engine.connect() as conn:
            update_query = text("""
                UPDATE biblio SET
                    title = :title,
                    sor = :sor,
                    classification = :classification,
                    publish_year = :publish_year,
                    isbn_issn = :isbn_issn,
                    call_number = :call_number,
                    edition = :edition,
                    collation = :collation,
                    series_title = :series_title,
                    spec_detail_info = :spec_detail_info,
                    notes = :notes,
                    predicted_jurusan = :predicted_jurusan,
                    predicted_confidence = :predicted_confidence,
                    predicted_multilabel = :predicted_multilabel,
                    last_update = NOW()
                WHERE biblio_id = :id
            """)

            conn.execute(update_query, {
                "title": title,
                "sor": data.get('sor', ''),
                "classification": classification,
                "publish_year": data.get('publish_year', ''),
                "isbn_issn": data.get('isbn_issn', ''),
                "call_number": data.get('call_number', ''),
                "edition": data.get('edition', ''),
                "collation": data.get('collation', ''),
                "series_title": data.get('series_title', ''),
                "spec_detail_info": data.get('spec_detail_info', ''),
                "notes": data.get('notes', ''),
                "predicted_jurusan": predicted_jur,
                "predicted_confidence": round(predicted_conf, 2),
                "predicted_multilabel": json.dumps(multilabel_data, ensure_ascii=False),
                "id": biblio_id
            })
            conn.commit()

        return jsonify({
            "message": f"Buku '{title}' berhasil diupdate.",
            "predicted_jurusan": predicted_jur,
            "predicted_confidence": round(predicted_conf, 2),
        }), 200

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Endpoint: DELETE /api/buku/delete/<int:biblio_id>
# Menghapus buku dari database
# ─────────────────────────────────────────────
@app.route('/api/buku/delete/<int:biblio_id>', methods=['DELETE', 'POST'])
def delete_buku_api(biblio_id):
    try:
        with engine.connect() as conn:
            delete_query = text("DELETE FROM biblio WHERE biblio_id = :id")
            conn.execute(delete_query, {"id": biblio_id})
            conn.commit()
        return jsonify({"message": "Buku berhasil dihapus."}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Endpoint: GET /api/status
# ─────────────────────────────────────────────
@app.route('/api/status', methods=['GET'])
def status():
    mode = model_info.get('mode', 'none')
    return jsonify({
        "status": "ok",
        "model_mode": mode,
        "text_classifier": clf_model is not None,
        "accuracy_cv": model_info.get('accuracy_cv', 0),
        "accuracy_test": model_info.get('accuracy_test', 0),
        "f1_cv_macro": model_info.get('f1_cv_macro', 0),
        "f1_cv_weighted": model_info.get('f1_cv_weighted', 0),
        "f1_test_macro": model_info.get('f1_test_macro', 0),
        "f1_test_weighted": model_info.get('f1_test_weighted', 0),
        "n_data_total": model_info.get('n_data', 0),
        "n_data_train": model_info.get('n_train', 0),
        "n_data_test": model_info.get('n_test', 0),
        "jurusan_pnj": model_info.get('jurusan_list', JURUSAN_LIST),
    })


# ─────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────
if __name__ == '__main__':
    mode = model_info.get('mode', 'none')
    print("=" * 60)
    print("  E-DDC Python AI API Server")
    print(f"  Mode       : {mode.upper()}")
    print(f"  Jurusan    : {len(JURUSAN_LIST)} kelas PNJ")
    print("  Server     : http://127.0.0.1:5000")
    print("  Search     : /api/buku/search?keyword=...")
    print("  Detail     : /api/buku/detail/<id>")
    print("  Status     : /api/status")
    print("=" * 60)
    if mode == 'hybrid':
        acc_test = model_info.get('accuracy_test', 0) * 100
        f1_test = model_info.get('f1_test_macro', 0) * 100
        print(f"[OK] Text Classifier dimuat (akurasi test: {acc_test:.1f}%, F1 test macro: {f1_test:.1f}%)")
    else:
        print("[WARNING] Model belum ada. Jalankan 'python train_model.py'")
    print("=" * 60)
    app.run(host='127.0.0.1', port=5000, debug=True)
# Trigger auto-reload to read updated pickle model

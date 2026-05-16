from flask import Flask, jsonify, request
from flask_cors import CORS
from sqlalchemy import create_engine, text
import numpy as np
import pickle
import re
import os

# Load .env jika ada
try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass

app = Flask(__name__)
CORS(app)

# ─────────────────────────────────────────────
# Konfigurasi Database
# ─────────────────────────────────────────────
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
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
    "Umum",
]

# Mapping DDC sub-kelas -> Jurusan PNJ (dipakai untuk fallback jika DDC map diperlukan)
def ddc_to_jurusan(kode_ddc_raw):
    """Map kode DDC ke Jurusan PNJ."""
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
            'accuracy_train': data.get('accuracy_train', 0),
            'n_data': data.get('n_data', 0),
            'jurusan_list': data.get('jurusan_list', JURUSAN_LIST),
        }
        print("[OK] Model HYBRID dimuat (Text Classifier)")
        return True

    print("[WARNING] Tidak ada model yang ditemukan!")
    return False

load_models()

# ─────────────────────────────────────────────
# Helper: Bersihkan kode DDC
# ─────────────────────────────────────────────
def clean_ddc(text_val):
    if text_val is None:
        return None
    match = re.search(r'(\d{3})', str(text_val).strip())
    if match:
        return int(match.group(1))
    return None

def build_text(title=None, description=None, notes=None):
    parts = []
    for val in [title, description, notes]:
        if val:
            s = str(val).strip()
            if s and s.lower() not in ('null', 'none', 'nan', ''):
                parts.append(s)
    return ' '.join(parts) if parts else ''

# ─────────────────────────────────────────────
# Keyword heuristic untuk koreksi prediksi rendah
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
        "fotografi", "photography", "illustrasi", "prepress",
    ],
    "Administrasi Niaga": [
        "manajemen", "management", "pemasaran", "marketing", "bisnis",
        "business", "perdagangan", "ekspor", "impor", "logistik",
        "sumber daya manusia", "sdm", "hrm", "organisasi", "kepemimpinan",
        "leadership", "wirausaha", "entrepreneur", "e-commerce",
    ],
    "Akuntansi": [
        "akuntansi", "accounting", "audit", "pajak", "tax", "neraca",
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

def keyword_boost(text, labels):
    """
    Jika prediksi top memiliki confidence rendah (<60%),
    gunakan keyword heuristic untuk membantu koreksi.
    """
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

    # Boost: naikkan probabilitas jurusan yang cocok keyword
    boosted = []
    total_boost = 15.0 * keyword_scores[best_jurusan]  # 15% per keyword match

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


# ─────────────────────────────────────────────
# Prediksi Multilabel -> Jurusan PNJ
# ─────────────────────────────────────────────
def predict_multilabel(ddc_value=None, book_text=None, ddc_raw=None, threshold=0.05):
    """
    Prediksi jurusan PNJ untuk sebuah buku.
    Prioritas: Text Classifier > Keyword Correction > DDC mapping fallback.
    """
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


# ─────────────────────────────────────────────
# Endpoint: GET /api/buku/search?keyword=...
# ─────────────────────────────────────────────
@app.route('/api/buku/search', methods=['GET'])
def search_buku():
    keyword = request.args.get('keyword', '').strip()
    filters = request.args.get('filters', '').strip()
    page = int(request.args.get('page', 1))
    per_page = min(int(request.args.get('per_page', 24)), 50)  # Max 50

    filter_list = [f.strip().lower() for f in filters.split(',')] if filters else []

    try:
        with engine.connect() as conn:
            # Hitung total khusus jika tidak ada keyword dan filter
            total_buku_db = 0
            if not keyword and not filters:
                total_query = text("SELECT COUNT(*) FROM biblio WHERE opac_hide = 0 AND classification IS NOT NULL AND classification != ''")
                total_buku_db = conn.execute(total_query).scalar()

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
                    b.notes
                FROM biblio b
                WHERE b.opac_hide = 0 
                  AND b.classification IS NOT NULL 
                  AND b.classification != ''
            """
            
            params = {}
            if keyword:
                m_range = re.match(r'^(\d{3})-(\d{3})$', keyword)
                if m_range:
                    start_val = int(m_range.group(1))
                    end_val = int(m_range.group(2))
                    query_base += f" AND (CAST(SUBSTRING(b.classification, 1, 3) AS UNSIGNED) BETWEEN {start_val} AND {end_val})"
                else:
                    query_base += " AND (b.title LIKE :kw OR b.sor LIKE :kw OR b.notes LIKE :kw)"
                    params["kw"] = f"%{keyword}%"
                
                query_base += " ORDER BY b.title ASC LIMIT 500" # Ambil lebih banyak untuk dipaginasi
            elif filters:
                query_base += " ORDER BY b.title ASC LIMIT 800" # Evaluasi up to 800 buku
            else:
                # Pagination query if no keyword and no filter
                offset = (page - 1) * per_page
                query_base += f" ORDER BY b.title ASC LIMIT {per_page} OFFSET {offset}"
            
            query = text(query_base)
            rows = conn.execute(query, params).mappings().all()

        hasil = []
        for row in rows:
            ddc_bersih = clean_ddc(row["classification"])
            if ddc_bersih is None:
                continue

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
            
            if filter_list and len(multilabel) > 0:
                match_found = False
                for item in multilabel:
                    label_lower = item['label'].lower()
                    if any(f in label_lower for f in filter_list):
                        match_found = True
                        break
                if not match_found:
                    continue

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
            }
            hasil.append(buku)
            
            # Jika menggunakan keyword/filter, batasi hasil yang dikumpulkan (misal max 200) agar tidak lambat
            if (keyword or filters) and len(hasil) >= 200:
                break

        # Terapkan pagination
        if not keyword and not filters:
            final_data = hasil
            total_items = total_buku_db
        else:
            total_items = len(hasil)
            offset = (page - 1) * per_page
            final_data = hasil[offset : offset + per_page]

        return jsonify({
            "data": final_data,
            "pagination": {
                "total": total_items,
                "page": page,
                "per_page": per_page,
                "total_pages": max(1, (total_items + per_page - 1) // per_page)
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
        })

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
        "accuracy_train": model_info.get('accuracy_train', 0),
        "n_data_trained": model_info.get('n_data', 0),
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
        acc = model_info.get('accuracy_cv', 0) * 100
        print(f"[OK] Text Classifier dimuat (akurasi CV: {acc:.1f}%)")
    else:
        print("[WARNING] Model belum ada. Jalankan 'python train_model.py'")
    print("=" * 60)
    app.run(host='127.0.0.1', port=5000, debug=True)

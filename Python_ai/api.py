from flask import Flask, jsonify, request
from flask_cors import CORS
from sqlalchemy import create_engine, text
import skfuzzy as fuzz
import numpy as np
import pickle
import re
import os

# Load .env jika ada
try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass  # python-dotenv opsional

app = Flask(__name__)
CORS(app)

# ─────────────────────────────────────────────
# Konfigurasi Database (baca dari .env atau environment)
# ─────────────────────────────────────────────
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine = create_engine(DB_URL)

# ─────────────────────────────────────────────
# Load Model FCM (Fuzzy C-Means)
# ─────────────────────────────────────────────
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'FUZZY_CENTERS.pickle')

def load_model():
    """Memuat pusat klaster FCM dari file pickle."""
    if not os.path.exists(MODEL_PATH):
        return None
    with open(MODEL_PATH, 'rb') as f:
        return pickle.load(f)

cntr = load_model()

# ─────────────────────────────────────────────
# Dynamic Mapping Klaster DDC → Program Studi PNJ
# ─────────────────────────────────────────────
def generate_dynamic_labels(centers):
    label_map = {}
    if centers is None:
        return label_map
        
    for idx, c in enumerate(centers):
        val = c[0]
        if val < 100:
            label_map[idx] = "Teknik Informatika & Komputer"
        elif 100 <= val < 300:
            label_map[idx] = "Lainnya (Agama, Bahasa, Umum)"
        elif 300 <= val < 400:
            label_map[idx] = "Akuntansi & Adm Niaga"
        elif 400 <= val < 500:
            label_map[idx] = "Lainnya (Agama, Bahasa, Umum)"
        elif 500 <= val < 600:
            label_map[idx] = "Matematika & Sains Terapan"
        elif 600 <= val < 700:
            label_map[idx] = "Teknik (Sipil, Mesin, Elektro)"
        elif 700 <= val < 900:
            label_map[idx] = "Teknik Grafika & Penerbitan"
        else:
            label_map[idx] = "Lainnya (Agama, Bahasa, Umum)"
    return label_map

DDC_LABEL_MAP = generate_dynamic_labels(cntr)

# ─────────────────────────────────────────────
# Helper: Bersihkan kode DDC ke angka 3 digit
# ─────────────────────────────────────────────
def clean_ddc(text_val):
    if text_val is None:
        return None
    match = re.search(r'(\d{3})', str(text_val).strip())
    if match:
        return int(match.group(1))
    return None

# ─────────────────────────────────────────────
# Helper: Prediksi Multilabel menggunakan FCM
# ─────────────────────────────────────────────
def predict_multilabel(ddc_value, threshold=0.05):
    """
    Mengembalikan daftar label beserta probabilitasnya.
    Hanya label dengan probabilitas >= threshold yang dikembalikan.
    """
    global cntr, DDC_LABEL_MAP
    if cntr is None:
        cntr = load_model()
        DDC_LABEL_MAP = generate_dynamic_labels(cntr)
    if cntr is None or ddc_value is None:
        return []

    # Format data untuk cmeans_predict (harus 2D array)
    test_data = np.array([[float(ddc_value)], [0.0]])

    # Prediksi keanggotaan fuzzy
    u, _, _, _, _, _ = fuzz.cluster.cmeans_predict(
        test_data, cntr, m=2.0, error=0.005, maxiter=1000
    )

    labels = []
    for klaster_idx, prob in enumerate(u[:, 0]):
        if prob >= threshold:
            labels.append({
                "klaster": klaster_idx,
                "label": DDC_LABEL_MAP.get(klaster_idx, f"Klaster {klaster_idx}"),
                "probabilitas": round(float(prob) * 100, 2)
            })

    # Urutkan dari probabilitas tertinggi
    labels.sort(key=lambda x: x["probabilitas"], reverse=True)
    return labels

# ─────────────────────────────────────────────
# Endpoint: GET /api/buku/search?keyword=...
# ─────────────────────────────────────────────
@app.route('/api/buku/search', methods=['GET'])
def search_buku():
    keyword = request.args.get('keyword', '').strip()
    filters = request.args.get('filters', '').strip()

    # Jika keduanya kosong, kembalikan hasil kosong atau default
    if not keyword and not filters:
        return jsonify([])

    filter_list = [f.strip().lower() for f in filters.split(',')] if filters else []

    try:
        global cntr
        if cntr is None:
            cntr = load_model()
            
        with engine.connect() as conn:
            query_base = """
                SELECT 
                    b.biblio_id,
                    b.title,
                    b.sor        AS author,
                    b.publish_year,
                    b.collation,
                    b.classification,
                    b.call_number,
                    b.image
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
                    query_base += " AND (b.title LIKE :kw OR b.sor LIKE :kw)"
                    params["kw"] = f"%{keyword}%"
                
            query_base += " ORDER BY b.title ASC LIMIT 100"
            
            query = text(query_base)
            rows = conn.execute(query, params).mappings().all()

        # Bangun response JSON dengan filter Multilabel
        hasil = []
        for row in rows:
            ddc_bersih = clean_ddc(row["classification"])
            if ddc_bersih is None:
                continue
                
            multilabel = predict_multilabel(ddc_bersih)
            
            # Jika ada filter dari checkbox, cek top label (probabilitas tertinggi)
            if filter_list and len(multilabel) > 0:
                top_label = multilabel[0]['label'].lower()
                # Jika top_label buku BUKAN salah satu dari filter yang dicentang, skip!
                if not any(f in top_label for f in filter_list):
                    continue

            buku = {
                "biblio_id":    row["biblio_id"],
                "Book_Title":   row["title"],
                "Author":       row["author"] or "Tidak Diketahui",
                "Year_Published": row["publish_year"] or "-",
                "Pages":        row["collation"] or "-",
                "Book_Code":    row["classification"] or "-",
                "Call_Number":  row["call_number"] or "-",
                "Publisher":    "-",
                "Image":        f"/repository/{row['image']}" if row['image'] else None,
                "DDC_Bersih":   ddc_bersih,
                "Multilabel":   multilabel,
            }
            hasil.append(buku)
            
            # Batasi hasil yang dikirim ke frontend 50 buku saja agar cepat
            if len(hasil) >= 50:
                break

        return jsonify(hasil)

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
                    b.biblio_id,
                    b.title,
                    b.sor        AS author,
                    b.edition,
                    b.isbn_issn,
                    b.publish_year,
                    b.collation,
                    b.series_title,
                    b.classification,
                    b.call_number,
                    b.notes,
                    b.image,
                    b.spec_detail_info
                FROM biblio b
                WHERE b.biblio_id = :id
                LIMIT 1
            """)
            row = conn.execute(query, {"id": biblio_id}).mappings().first()

        if not row:
            return jsonify({"error": "Buku tidak ditemukan."}), 404

        ddc_bersih = clean_ddc(row["classification"])
        multilabel = predict_multilabel(ddc_bersih)

        return jsonify({
            "biblio_id":    row["biblio_id"],
            "Book_Title":   row["title"],
            "Author":       row["author"] or "-",
            "Edition":      row["edition"] or "-",
            "ISBN":         row["isbn_issn"] or "-",
            "Year_Published": row["publish_year"] or "-",
            "Pages":        row["collation"] or "-",
            "Series":       row["series_title"] or "-",
            "Book_Code":    row["classification"] or "-",
            "Call_Number":  row["call_number"] or "-",
            "Notes":        row["notes"] or "-",
            "Publisher":    "-",
            "Place":        "-",
            "Image":        row["image"] or None,
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
    model_loaded = cntr is not None
    return jsonify({
        "status": "ok",
        "model_loaded": model_loaded,
        "n_clusters": int(cntr.shape[0]) if model_loaded else 0,
        "ddc_label_map": DDC_LABEL_MAP
    })


# ─────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────
if __name__ == '__main__':
    print("=" * 55)
    print("  E-DDC Python AI API Server")
    print("  Berjalan di  : http://127.0.0.1:5000")
    print("  Endpoint     : /api/buku/search?keyword=...")
    print("  Status       : /api/status")
    print("=" * 55)
    if cntr is None:
        print("[WARNING]  PERINGATAN: Model FUZZY_CENTERS.pickle belum ada.")
        print("           Jalankan dulu: python train_model.py")
    else:
        print(f"[OK]  Model FCM dimuat ({cntr.shape[0]} klaster).")
    print("=" * 55)
    app.run(host='127.0.0.1', port=5000, debug=True)

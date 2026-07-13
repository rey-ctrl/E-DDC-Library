import os
import re
import csv
from sqlalchemy import create_engine, text
from dotenv import load_dotenv

# Load main .env file using absolute path
load_dotenv("c:/Users/Adit/Documents/My Life/Skripsi/E-DDC/.env", override=True)

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_DATABASE') or os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASSWORD') if os.getenv('DB_PASSWORD') is not None else os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine  = create_engine(DB_URL)

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

# Load biblio_ids from CSV
csv_path = "dataset_opac_8740.csv"
if not os.path.exists(csv_path):
    # try parent directory
    csv_path = "../dataset_opac_8740.csv"

biblio_ids = []
with open(csv_path, mode='r', encoding='utf-8') as f:
    reader = csv.reader(f, delimiter=';')
    header = next(reader)
    for row in reader:
        if row:
            biblio_ids.append(int(row[0]))

# Query the database
with engine.connect() as conn:
    query = text("SELECT biblio_id, classification, predicted_jurusan FROM biblio WHERE biblio_id IN :ids")
    res = conn.execute(query, {"ids": biblio_ids})
    rows = res.fetchall()

total = len(rows)

initial_counts = {}
predicted_counts = {}

jurusans = [
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
    "Umum"
]

for j in jurusans:
    initial_counts[j] = 0
    predicted_counts[j] = 0

for row in rows:
    initial_j = ddc_to_jurusan(row[1])
    pred_j = row[2]
    
    if initial_j in initial_counts:
        initial_counts[initial_j] += 1
    else:
        initial_counts["Umum"] += 1
        
    if pred_j in predicted_counts:
        predicted_counts[pred_j] += 1
    elif pred_j is not None:
        predicted_counts["Umum"] += 1

print(f"Total data buku yang dianalisis: {total}")
print("\n=== Tabel Distribusi Frekuensi Klasifikasi ===")
print("| Program Studi | Frekuensi DDC Awal | DDC Awal | Frekuensi Koreksi AI | Koreksi AI |")
print("| --- | --- | --- | --- | --- |")
for j in jurusans:
    init_f = initial_counts[j]
    init_pct = (init_f / total) * 100
    pred_f = predicted_counts[j]
    pred_pct = (pred_f / total) * 100
    init_f_str = f"{init_f:,}".replace(",", ".")
    pred_f_str = f"{pred_f:,}".replace(",", ".")
    print(f"| {j} | {init_f_str} | {init_pct:.2f}% | {pred_f_str} | {pred_pct:.2f}% |")
print("==============================================")


import pymysql, os, re, sys, pickle
sys.path.insert(0, os.path.dirname(__file__))

from dotenv import load_dotenv
load_dotenv()

# Load models
with open('MODEL_HYBRID.pickle', 'rb') as f:
    data = pickle.load(f)
tfidf = data['tfidf']
clf = data['clf']
classes = clf.classes_

KEYWORD_HINTS = {
    "Teknik Informatika & Komputer": [
        "pemrograman","programming","algoritma","database","jaringan","komputer","computer","software",
        "basis data","data mining","big data","machine learning","kecerdasan buatan",
    ],
    "Akuntansi": ["akuntansi","accounting","audit","pajak","tax","neraca","laporan keuangan","financial","anggaran"],
    "Matematika": ["matematika","mathematics","kalkulus","calculus","aljabar","algebra","statistik","statistika","diskrit","matriks"],
    "Administrasi Niaga": ["manajemen","management","pemasaran","marketing","bisnis","business","perdagangan"],
    "Teknik Mesin": ["mesin","engine","turbin","pompa","manufaktur","otomotif"],
    "Teknik Elektro": ["elektronika","rangkaian","listrik","electrical","mikrokontroler","robot"],
    "Teknik Sipil": ["beton","konstruksi","bangunan","jembatan","struktur","pondasi"],
    "Sains": ["sains","biologi","kimia","fisika","ekologi"],
}

conn = pymysql.connect(
    host=os.getenv('DB_HOST','127.0.0.1'),
    port=int(os.getenv('DB_PORT','3304')),
    user=os.getenv('DB_USER','root'),
    password=os.getenv('DB_PASS',''),
    database=os.getenv('DB_NAME','opac')
)
cur = conn.cursor(pymysql.cursors.DictCursor)

# Ambil buku yang mempunyai kata kunci kuat di judul
cur.execute("""
    SELECT biblio_id, title, sor, classification, notes
    FROM biblio
    WHERE opac_hide = 0
    AND classification IS NOT NULL AND classification != ''
    AND classification REGEXP '^[0-9]'
    LIMIT 3000
""")
rows = cur.fetchall()
conn.close()

candidates = []
for r in rows:
    title = (r['title'] or '')
    text = title.strip()
    if not text:
        continue
    
    text_lower = text.lower()
    
    # Prediksi tanpa boost
    X = tfidf.transform([text])
    probas = clf.predict_proba(X)[0]
    labels_pre = sorted(zip(classes, [round(float(p)*100,2) for p in probas]), key=lambda x: x[1], reverse=True)
    top_label_pre, top_conf_pre = labels_pre[0]
    
    # Hanya ambil kasus dengan confidence rendah < 60
    if top_conf_pre >= 60.0:
        continue
    
    # Cek apakah ada keyword yang cocok ke jurusan BERBEDA dari prediksi
    keyword_scores = {}
    for jurusan, keywords in KEYWORD_HINTS.items():
        score = sum(1 for kw in keywords if kw in text_lower)
        if score > 0:
            keyword_scores[jurusan] = score
    
    if not keyword_scores:
        continue
    
    best_jurusan = max(keyword_scores, key=keyword_scores.get)
    if best_jurusan == top_label_pre:
        # Keyword cocok dgn prediksi - menarik, tapi kita cari yg berbeda
        pass
    
    # Hitung boost
    total_boost = 15.0 * keyword_scores[best_jurusan]
    boosted_labels = []
    for jur, prob in labels_pre:
        if jur == best_jurusan:
            new_prob = min(99.0, prob + total_boost)
        else:
            reduction = total_boost / max(len(labels_pre) - 1, 1)
            new_prob = max(0.0, prob - reduction)
        boosted_labels.append((jur, new_prob))
    
    total = sum(p for _, p in boosted_labels)
    boosted_labels = [(j, round(p/total*100,2)) for j, p in boosted_labels]
    boosted_labels.sort(key=lambda x: x[1], reverse=True)
    
    top_label_post, top_conf_post = boosted_labels[0]
    
    # Kasus menarik: boost mengubah top-label ke jurusan yang tepat secara logis
    if top_conf_post > top_conf_pre and keyword_scores.get(best_jurusan, 0) > 0:
        ddc_m = re.search(r'(\d{3})', str(r['classification']))
        ddc_int = int(ddc_m.group(1)) if ddc_m else 0
        candidates.append({
            'id': r['biblio_id'],
            'title': r['title'],
            'ddc': r['classification'],
            'ddc_int': ddc_int,
            'pre_top': top_label_pre,
            'pre_conf': top_conf_pre,
            'post_top': top_label_post,
            'post_conf': top_conf_post,
            'keyword_hit': best_jurusan,
            'kw_score': keyword_scores[best_jurusan],
            'boost': total_boost,
        })

# Pilih 5 kasus terbaik (boost paling dramatis & berbeda label)
diff_cases = [c for c in candidates if c['pre_top'] != c['post_top']]
diff_cases.sort(key=lambda x: x['post_conf'] - x['pre_conf'], reverse=True)
same_cases = [c for c in candidates if c['pre_top'] == c['post_top']]
same_cases.sort(key=lambda x: x['post_conf'] - x['pre_conf'], reverse=True)

print("=" * 70)
print("KASUS DRAMATIS: Boost MENGUBAH Top Label")
print("=" * 70)
for c in diff_cases[:3]:
    print(f"\nID     : {c['id']}")
    print(f"Judul  : {c['title'][:70]}")
    print(f"DDC    : {c['ddc']} (int={c['ddc_int']})")
    print(f"SEBELUM: {c['pre_top']} -> {c['pre_conf']:.1f}%")
    print(f"SESUDAH: {c['post_top']} -> {c['post_conf']:.1f}%")
    print(f"Keyword: '{c['keyword_hit']}' (+{c['boost']:.0f}% boost)")

print("\n" + "=" * 70)
print("KASUS: Boost MEMPERKUAT Top Label yang Sudah Benar")
print("=" * 70)
for c in same_cases[:3]:
    print(f"\nID     : {c['id']}")
    print(f"Judul  : {c['title'][:70]}")
    print(f"DDC    : {c['ddc']} (int={c['ddc_int']})")
    print(f"SEBELUM: {c['pre_top']} -> {c['pre_conf']:.1f}%")
    print(f"SESUDAH: {c['post_top']} -> {c['post_conf']:.1f}%")
    print(f"Keyword: '{c['keyword_hit']}' (+{c['boost']:.0f}% boost)")

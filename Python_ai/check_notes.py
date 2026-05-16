from sqlalchemy import create_engine, text
import os
try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))
except ImportError:
    pass

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine = create_engine(DB_URL)

with engine.connect() as conn:
    total = conn.execute(text(
        "SELECT COUNT(*) FROM biblio WHERE opac_hide=0 AND classification IS NOT NULL AND classification != ''"
    )).scalar()

    with_notes = conn.execute(text(
        "SELECT COUNT(*) FROM biblio WHERE opac_hide=0 AND classification IS NOT NULL AND classification != '' "
        "AND notes IS NOT NULL AND notes != '' AND LOWER(TRIM(notes)) NOT IN ('null','none','nan')"
    )).scalar()

    with_desc = conn.execute(text(
        "SELECT COUNT(*) FROM biblio WHERE opac_hide=0 AND classification IS NOT NULL AND classification != '' "
        "AND spec_detail_info IS NOT NULL AND spec_detail_info != '' AND LOWER(TRIM(spec_detail_info)) NOT IN ('null','none','nan')"
    )).scalar()

    print(f"Total buku valid    : {total}")
    print(f"Buku dengan notes   : {with_notes} ({with_notes/total*100:.1f}%)")
    print(f"Buku dengan deskripsi: {with_desc} ({with_desc/total*100:.1f}%)")
    print(f"Buku tanpa notes    : {total - with_notes}")
    print()

    # Contoh buku yang punya notes
    rows = conn.execute(text(
        "SELECT title, notes, spec_detail_info, classification FROM biblio "
        "WHERE opac_hide=0 AND classification IS NOT NULL AND classification != '' "
        "AND notes IS NOT NULL AND notes != '' AND LOWER(TRIM(notes)) NOT IN ('null','none','nan') "
        "LIMIT 8"
    )).mappings().all()

    print("=== Contoh buku dengan notes ===")
    for r in rows:
        print(f"\nTitle: {r['title'][:70]}")
        print(f"  DDC  : {r['classification']}")
        print(f"  Notes: {str(r['notes'])[:150]}")
        desc = str(r['spec_detail_info'] or '')[:80]
        print(f"  Desc : {desc}")

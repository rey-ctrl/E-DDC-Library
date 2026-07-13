import os
from sqlalchemy import create_engine, text
from dotenv import load_dotenv

load_dotenv("c:/Users/Adit/Documents/My Life/Skripsi/E-DDC/.env", override=True)

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_DATABASE') or os.getenv('DB_NAME', 'opac')
DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASSWORD') if os.getenv('DB_PASSWORD') is not None else os.getenv('DB_PASS', '')
DB_URL  = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine  = create_engine(DB_URL)

with engine.connect() as conn:
    query = text("SELECT biblio_id, title, classification, predicted_multilabel FROM biblio WHERE title LIKE '%Procedure Handbook%Arc Welding%'")
    rows = conn.execute(query).fetchall()

for row in rows:
    print(f"ID: {row[0]} | Title: {row[1]} | Classification: {row[2]} | Multilabel: {row[3]}")

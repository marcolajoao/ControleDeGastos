from datetime import datetime
import pandas as pd
from sqlalchemy import create_engine, text

# 1. Conexão
usuario = 'root'
senha = 'root'
host = 'localhost'
porta = 3306
banco = 'TESTE'
caminho_csv = 'Nubank_2025-09-09.csv'

# 2. Lê CSV
df = pd.read_csv(caminho_csv)

# 3. Adiciona data de inclusão e categoria vazia
df['data_inclusao'] = datetime.now()
df['categoria'] = None

# 4. Cria engine e define a tabela
engine = create_engine(f'mysql+pymysql://{usuario}:{senha}@{host}:{porta}/{banco}')

# 5. Criação da tabela
create_table_query = """
CREATE TABLE IF NOT EXISTS TESTE (
    NNUMEGASTO INT AUTO_INCREMENT PRIMARY KEY,
    DDATAGASTO DATE,
    CDESCGASTO VARCHAR(255),
    NVALOGASTO DECIMAL(10,2),
    data_inclusao DATETIME DEFAULT CURRENT_TIMESTAMP,
    categoria VARCHAR(100) DEFAULT NULL
);
"""

with engine.connect() as conn:
    conn.execute(text(create_table_query))

# 6. Inserção dos dados
df.to_sql('TESTE', con=engine, if_exists='append', index=False)
print("Importação concluída com sucesso!")

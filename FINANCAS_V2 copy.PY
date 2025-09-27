from datetime import datetime
import pandas as pd
from sqlalchemy import create_engine, text

# 1. Configurações de conexão e variável obrigatória
DB_CREDENCIAIS = {
    'usuario': 'root',
    'senha': 'root',
    'host': 'localhost',
    'porta': 3306,
    'banco': 'TESTE'
}

# VARIÁVEL OBRIGATÓRIA - Defina o código da pessoa quando for executar
NNUMEPESS = 1  # ← Altere este valor antes de executar o script!

# Verifica se a variável foi definida
if NNUMEPESS is None:
    raise ValueError("Você deve definir o valor de NNUMEPESS antes de executar o script!")

# 2. Lê CSV com tratamento de erros
try:
    LE_PLANILHA = pd.read_csv('Nubank_2025-09-09.csv', parse_dates=['date'])
except Exception as e:
    print(f"Erro ao ler arquivo CSV: {e}")
    exit()

# 3. Mapeamento para os novos campos
LE_PLANILHA.rename(columns={
    'date': 'DREALGASTO',
    'title': 'CDESCGASTO',
    'amount': 'NVALOGASTO'
}, inplace=True)

# 4. Adiciona novos campos com NNUMEPESS obrigatório
LE_PLANILHA['DINCLUGASTO'] = datetime.now()
LE_PLANILHA['CCATEGASTO'] = None
LE_PLANILHA['IGNORA_GASTO'] = 'N'
LE_PLANILHA['NNUMEPESS'] = NNUMEPESS  # Usa o valor definido na variável

# 5. Conexão com tratamento de erros
try:
    engine = create_engine(
        f"mysql+pymysql://{DB_CREDENCIAIS['usuario']}:{DB_CREDENCIAIS['senha']}@"
        f"{DB_CREDENCIAIS['host']}:{DB_CREDENCIAIS['porta']}/{DB_CREDENCIAIS['banco']}"
    )
except Exception as e:
    print(f"Erro na conexão com o banco: {e}")
    exit()

# 6. Execução da importação
try:
    with engine.connect() as conn:
        # Verifica se a tabela de pessoas existe (opcional)
        result = conn.execute(text(f"SELECT 1 FROM HSSPESS WHERE NNUMEPESS = {NNUMEPESS} LIMIT 1"))
        if not result.fetchone():
            raise ValueError(f"NNUMEPESS {NNUMEPESS} não encontrado na tabela HSSPESS!")
        
        # Insere os dados
        LE_PLANILHA.to_sql('TESTE', con=engine, if_exists='append', index=False)
    
    print(f"Importação concluída com sucesso para NNUMEPESS = {NNUMEPESS}!")
    
except Exception as e:
    print(f"Erro durante a importação: {e}")

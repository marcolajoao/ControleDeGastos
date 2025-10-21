import tkinter as tk
from tkinter import ttk, messagebox
import mysql.connector
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg
import matplotlib.pyplot as plt
from matplotlib.figure import Figure
import pandas as pd
from datetime import datetime

class AppGastosNubank:
    def __init__(self, root):
        self.root = root
        self.root.title("Análise de Gastos - Nubank MySQL")
        
        # Configuração padrão da conexão MySQL (com suporte a MySQL 8.0+)
        self.config = {
            'host': 'localhost',
            'user': 'root',          # Substitua pelo seu usuário
            'password': 'root',      # Substitua pela sua senha
            'database': 'GASTO',     # Substitua pelo nome do seu banco
            'port': 3306,            # Porta padrão do MySQL
            'auth_plugin': 'mysql_native_password'  # Corrige o erro de autenticação
        }
        
        # Variáveis de controle
        self.anos_disponiveis = []
        self.meses_disponiveis = [
            ('Todos os Meses', 0),
            ('Janeiro', 1), ('Fevereiro', 2), ('Março', 3), ('Abril', 4),
            ('Maio', 5), ('Junho', 6), ('Julho', 7), ('Agosto', 8),
            ('Setembro', 9), ('Outubro', 10), ('Novembro', 11), ('Dezembro', 12)
        ]
        
        self.criar_widgets()
        self.testar_conexao()
    
    def criar_widgets(self):
        # Frame de configuração do banco (AGORA COM CAMPO PARA PORTA)
        frame_config = ttk.LabelFrame(self.root, text="Configuração do MySQL", padding=10)
        frame_config.grid(row=0, column=0, padx=10, pady=5, sticky="ew")
        
        # Host
        ttk.Label(frame_config, text="Host:").grid(row=0, column=0, sticky="e")
        self.host_entry = ttk.Entry(frame_config)
        self.host_entry.grid(row=0, column=1, padx=5, pady=2, sticky="ew")
        self.host_entry.insert(0, self.config['host'])
        
        # Porta
        ttk.Label(frame_config, text="Porta:").grid(row=0, column=2, sticky="e")
        self.port_entry = ttk.Entry(frame_config, width=8)
        self.port_entry.grid(row=0, column=3, padx=5, pady=2)
        self.port_entry.insert(0, str(self.config['port']))
        
        # Usuário
        ttk.Label(frame_config, text="Usuário:").grid(row=1, column=0, sticky="e")
        self.user_entry = ttk.Entry(frame_config)
        self.user_entry.grid(row=1, column=1, padx=5, pady=2, sticky="ew")
        self.user_entry.insert(0, self.config['user'])
        
        # Senha
        ttk.Label(frame_config, text="Senha:").grid(row=1, column=2, sticky="e")
        self.pass_entry = ttk.Entry(frame_config, show="*")
        self.pass_entry.grid(row=1, column=3, padx=5, pady=2)
        self.pass_entry.insert(0, self.config['password'])
        
        # Banco de dados
        ttk.Label(frame_config, text="Banco:").grid(row=2, column=0, sticky="e")
        self.db_entry = ttk.Entry(frame_config)
        self.db_entry.grid(row=2, column=1, padx=5, pady=2, sticky="ew")
        self.db_entry.insert(0, self.config['database'])
        
        # Botão de teste de conexão
        ttk.Button(frame_config, text="Testar Conexão", command=self.testar_conexao).grid(row=3, column=0, columnspan=4, pady=5)
        
        # Frame de consulta
        frame_consulta = ttk.LabelFrame(self.root, text="Filtrar Gastos", padding=10)
        frame_consulta.grid(row=1, column=0, padx=10, pady=5, sticky="ew")
        
        # Combobox para Ano
        ttk.Label(frame_consulta, text="Ano:").grid(row=0, column=0, sticky="e")
        self.ano_combo = ttk.Combobox(frame_consulta, state="readonly")
        self.ano_combo.grid(row=0, column=1, padx=5, pady=2, sticky="ew")
        
        # Combobox para Mês
        ttk.Label(frame_consulta, text="Mês:").grid(row=1, column=0, sticky="e")
        self.mes_combo = ttk.Combobox(frame_consulta, state="readonly")
        self.mes_combo.grid(row=1, column=1, padx=5, pady=2, sticky="ew")
        self.mes_combo['values'] = [mes[0] for mes in self.meses_disponiveis]
        self.mes_combo.current(0)
        
        # Combobox para Categoria
        ttk.Label(frame_consulta, text="Categoria:").grid(row=2, column=0, sticky="e")
        self.categoria_combo = ttk.Combobox(frame_consulta, state="readonly")
        self.categoria_combo.grid(row=2, column=1, padx=5, pady=2, sticky="ew")
        self.categoria_combo.set('Todas as Categorias')

        # Combobox para PESSOA
        ttk.Label(frame_consulta, text="Pessoa:").grid(row=4, column=0, sticky="e")
        self.PESSOA_combo = ttk.Combobox(frame_consulta, state="readonly")
        self.PESSOA_combo.grid(row=4, column=1, padx=5, pady=2, sticky="ew")
        self.PESSOA_combo.set('Todas as Pessoas')
        
        # Checkbox para ignorar gastos marcados
        self.ignorar_gastos_var = tk.BooleanVar(value=True)
        ttk.Checkbutton(frame_consulta, text="Ignorar gastos marcados", variable=self.ignorar_gastos_var).grid(row=3, column=0, columnspan=2)
        
        # Botão de consulta
        ttk.Button(frame_consulta, text="Consultar Gastos", command=self.consultar_gastos).grid(row=5, column=0, columnspan=2, pady=5)
        
        # Frame de resultados
        frame_resultados = ttk.LabelFrame(self.root, text="Resultados e Gráficos", padding=10)
        frame_resultados.grid(row=2, column=0, padx=10, pady=5, sticky="nsew")
        
        # Gráfico (Matplotlib)
        self.figura = Figure(figsize=(8, 4), dpi=100)
        self.ax = self.figura.add_subplot(111)
        self.canvas = FigureCanvasTkAgg(self.figura, master=frame_resultados)
        self.canvas.get_tk_widget().pack(fill=tk.BOTH, expand=True)
        
        # Tabela de resultados (Treeview)
        self.tree = ttk.Treeview(frame_resultados, columns=('Data', 'Descrição', 'Valor', 'Categoria', 'Pessoa'), show='headings')
        self.tree.heading('Data', text='Data')
        self.tree.heading('Descrição', text='Descrição')
        self.tree.heading('Valor', text='Valor (R$)')
        self.tree.heading('Categoria', text='Categoria')
        self.tree.heading('Pessoa', text='Pessoa')
        self.tree.column('Data', width=100)
        self.tree.column('Descrição', width=250)
        self.tree.column('Valor', width=100)
        self.tree.column('Categoria', width=150)
        self.tree.column('Pessoa', width=150)
        
        # Scrollbar para a tabela
        scrollbar = ttk.Scrollbar(frame_resultados, orient=tk.VERTICAL, command=self.tree.yview)
        self.tree.configure(yscroll=scrollbar.set)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.tree.pack(fill=tk.BOTH, expand=True)
        
        # Barra de status
        self.status_var = tk.StringVar()
        self.status_var.set("Pronto para conectar")
        ttk.Label(self.root, textvariable=self.status_var, relief=tk.SUNKEN).grid(row=3, column=0, sticky="ew", padx=10, pady=5)
        
        # Configurar expansão da janela
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(2, weight=1)
    
    def testar_conexao(self):
        try:
            # Obter configuração da interface
            config = {
                'host': self.host_entry.get() or 'localhost',
                'port': int(self.port_entry.get() or 3306),
                'user': self.user_entry.get() or 'root',
                'password': self.pass_entry.get(),
                'database': self.db_entry.get(),
                'auth_plugin': 'mysql_native_password',
                'connection_timeout': 5
            }
            
            # Tentativa de conexão
            conn = mysql.connector.connect(**config)
            cursor = conn.cursor()
            
            # Verificar se a tabela existe
            cursor.execute("SHOW TABLES LIKE 'gastos_nubank'")
            if not cursor.fetchone():
                messagebox.showwarning("Aviso", "Tabela 'gastos_nubank' não encontrada no banco!")
            
            # Carregar anos disponíveis
            cursor.execute("""
                SELECT YEAR(DREALGASTO) 
                FROM gastos_nubank 
                WHERE IGNORA_GASTO = 'N' 
                GROUP BY YEAR(DREALGASTO) 
                ORDER BY YEAR(DREALGASTO) DESC
            """)
            self.anos_disponiveis = [str(ano[0]) for ano in cursor.fetchall()]
            self.ano_combo['values'] = self.anos_disponiveis
            if self.anos_disponiveis:
                self.ano_combo.current(0)
            
            # Carregar categorias disponíveis
            cursor.execute("""
                SELECT DISTINCT CCATEGASTO 
                FROM gastos_nubank 
                WHERE CCATEGASTO IS NOT NULL 
                AND IGNORA_GASTO = 'N' 
                ORDER BY CCATEGASTO
            """)
            categorias = ['Todas as Categorias'] + [cat[0] for cat in cursor.fetchall()]
            self.categoria_combo['values'] = categorias
            self.categoria_combo.current(0)

            # Carregar PESSOAS disponíveis (AGORA COM ID E NOME)
            cursor.execute("SELECT NNUMEPESS, CNOMEPESS FROM HSSPESS;")
            self.PESSOAS_disponiveis = [(0, 'Todas as Pessoas')] + [(pessoa[0], pessoa[1]) for pessoa in cursor.fetchall()]
            self.PESSOA_combo['values'] = [pessoa[1] for pessoa in self.PESSOAS_disponiveis]  # Mostra só os nomes
            self.PESSOA_combo.current(0)
            
            conn.close()
            messagebox.showinfo("Sucesso", "Conexão bem-sucedida!\nDados carregados para consulta.")
            self.status_var.set(f"Conectado a {config['host']}:{config['port']}")
            
        except mysql.connector.errors.ProgrammingError as e:
            if "1045" in str(e):
                messagebox.showerror("Erro", "Acesso negado: Usuário ou senha incorretos")
            elif "1049" in str(e):
                messagebox.showerror("Erro", f"Banco de dados '{config['database']}' não existe")
            else:
                messagebox.showerror("Erro SQL", f"Erro na consulta: {e}")
            self.status_var.set("Erro na conexão")
            
        except mysql.connector.errors.InterfaceError as e:
            if "2003" in str(e):
                messagebox.showerror("Erro", f"Não foi possível conectar ao MySQL na porta {config['port']}.\n"
                                          "Verifique:\n"
                                          "1. Se o MySQL está rodando\n"
                                          "2. Se a porta está correta\n"
                                          "3. Se o firewall permite a conexão")
            else:
                messagebox.showerror("Erro", f"Problema de conexão: {e}")
            self.status_var.set("Falha na conexão")
            
        except mysql.connector.errors.NotSupportedError:
            messagebox.showerror("Erro de Autenticação", 
                               "O plugin de autenticação não é suportado.\n"
                               "Solução:\n"
                               "1. Execute no MySQL:\n"
                               "ALTER USER 'seu_usuario'@'localhost' IDENTIFIED WITH mysql_native_password BY 'sua_senha';\n"
                               "2. FLUSH PRIVILEGES;")
            self.status_var.set("Erro: Plugin não suportado")
            
        except Exception as e:
            messagebox.showerror("Erro Inesperado", f"Ocorreu um erro: {str(e)}")
            self.status_var.set("Erro inesperado")
    
    def consultar_gastos(self):
        if not self.ano_combo.get():
            messagebox.showwarning("Aviso", "Selecione um ano para consulta")
            return
            
        try:
            config = {
                'host': self.host_entry.get() or 'localhost',
                'port': int(self.port_entry.get() or 3306),
                'user': self.user_entry.get() or 'root',
                'password': self.pass_entry.get(),
                'database': self.db_entry.get(),
                'auth_plugin': 'mysql_native_password'
            }
            
            conn = mysql.connector.connect(**config)
            cursor = conn.cursor(dictionary=True)
            
            # Construir query base MODIFICADA (com JOIN para pessoa)
            query = """
                SELECT 
                    DATE_FORMAT(g.DREALGASTO, '%d/%m/%Y') as data_formatada,
                    g.CDESCGASTO as descricao,
                    g.NVALOGASTO as valor,
                    g.CCATEGASTO as categoria,
                    p.CNOMEPESS as nome_pessoa
                FROM gastos_nubank g
                LEFT JOIN HSSPESS p ON g.NNUMEPESS = p.NNUMEPESS
                WHERE YEAR(g.DREALGASTO) = %s
                AND g.IGNORA_GASTO = %s
            """
            params = [self.ano_combo.get(), 'Y' if not self.ignorar_gastos_var.get() else 'N']
            
            # Adicionar filtro de mês se selecionado
            mes_selecionado = self.mes_combo.current()
            if mes_selecionado > 0:
                query += " AND MONTH(g.DREALGASTO) = %s"
                params.append(self.meses_disponiveis[mes_selecionado][1])
            
            # Adicionar filtro de PESSOA se selecionado (CORRIGIDO)
            if hasattr(self, 'PESSOAS_disponiveis'):
                pessoa_selecionada = self.PESSOA_combo.current()
                if pessoa_selecionada > 0:  # Ignora a opção "Todas as Pessoas" (índice 0)
                    query += " AND g.NNUMEPESS = %s"
                    params.append(self.PESSOAS_disponiveis[pessoa_selecionada][0])  # Pega o ID
            
            # Adicionar filtro de categoria se selecionado
            if self.categoria_combo.get() != 'Todas as Categorias':
                query += " AND g.CCATEGASTO = %s"
                params.append(self.categoria_combo.get())
            
            query += " ORDER BY g.DREALGASTO DESC"
            
            cursor.execute(query, params)
            resultados = cursor.fetchall()
            
            # Limpar treeview
            for item in self.tree.get_children():
                self.tree.delete(item)
                
            # Preencher treeview (AGORA COM PESSOA)
            total = 0
            for row in resultados:
                self.tree.insert('', tk.END, values=(
                    row['data_formatada'],
                    row['descricao'],
                    f"R$ {row['valor']:.2f}",
                    row['categoria'] if row['categoria'] else 'Sem Categoria',
                    row['nome_pessoa'] if row['nome_pessoa'] else 'Sem Pessoa'
                ))
                total += float(row['valor'])
            
            # Atualizar status
            self.status_var.set(f"Total: R$ {total:.2f} | {len(resultados)} registros | {config['host']}:{config['port']}")
            
            # Gerar gráfico
            self.gerar_grafico(resultados)
            
            conn.close()
            
        except Exception as e:
            messagebox.showerror("Erro", f"Falha na consulta:\n{str(e)}")
            self.status_var.set("Erro na consulta")
    
    def gerar_grafico(self, dados):
        self.ax.clear()
        
        if not dados:
            self.ax.text(0.5, 0.5, 'Nenhum dado para exibir', ha='center', va='center')
            self.canvas.draw()
            return
        
        try:
            df = pd.DataFrame(dados)
            df['valor'] = df['valor'].astype(float)
            df['data'] = pd.to_datetime(df['data_formatada'], format='%d/%m/%Y')
            
            # Agrupar por mês e calcular totais
            df_agrupado = df.groupby(df['data'].dt.month)['valor'].sum()
            
            # Nomes dos meses
            meses_nomes = [mes[0] for mes in self.meses_disponiveis[1:]]
            
            # Criar gráfico de barras
            cores = plt.cm.Purples_r(range(len(df_agrupado)))
            self.ax.bar(meses_nomes[:len(df_agrupado)], df_agrupado, color=cores)
            
            # Configurações do gráfico
            self.ax.set_title(f"Gastos por Mês - {self.ano_combo.get()}")
            self.ax.set_xlabel("Mês")
            self.ax.set_ylabel("Valor Gasto (R$)")
            self.ax.tick_params(axis='x', rotation=45)
            self.ax.grid(axis='y', linestyle='--', alpha=0.7)
            
            # Adicionar valores nas barras
            for i, valor in enumerate(df_agrupado):
                self.ax.text(i, valor + 5, f"R$ {valor:.2f}", ha='center', fontsize=9)
            
            self.figura.tight_layout()
            self.canvas.draw()
            
        except Exception as e:
            self.ax.text(0.5, 0.5, f'Erro ao gerar gráfico:\n{str(e)}', ha='center', va='center')
            self.canvas.draw()

if __name__ == "__main__":
    root = tk.Tk()
    app = AppGastosNubank(root)
    root.mainloop()
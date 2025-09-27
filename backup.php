<?php
// index.php
session_start();

// Configurações do banco (ALTERE ESTES VALORES!)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'GASTO');

class MySQLBackup {
    private $conn;
    private $backup_dir;
    private $errors = [];

    public function __construct() {
        $this->backup_dir = __DIR__ . '/backups/';
        $this->checkPermissions();
    }

    private function checkPermissions() {
        // Verificar se a pasta existe
        if (!file_exists($this->backup_dir)) {
            if (!mkdir($this->backup_dir, 0755, true)) {
                $this->errors[] = "Não foi possível criar a pasta backups. Verifique as permissões!";
                return false;
            }
        }

        // Verificar se é possível escrever na pasta
        if (!is_writable($this->backup_dir)) {
            $this->errors[] = "Pasta backups sem permissão de escrita. Execute: chmod 755 backups";
            return false;
        }

        return true;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            throw new Exception("Conexão falhou: " . $this->conn->connect_error);
        }
        return true;
    }

    public function createBackup($compress = true) {
        // Verificar se há erros de permissão primeiro
        if (!empty($this->errors)) {
            throw new Exception(implode("\n", $this->errors));
        }

        $this->connect();
        
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s');
        $sql_file = $this->backup_dir . $filename . '.sql';
        $final_file = $compress ? $this->backup_dir . $filename . '.sql.gz' : $sql_file;

        // Gerar backup
        $output = $this->generateBackup();
        
        // Tentar escrever o arquivo
        if (file_put_contents($sql_file, $output) === false) {
            throw new Exception("Não foi possível escrever o arquivo. Verifique as permissões da pasta backups!");
        }

        // Compactar se necessário
        if ($compress) {
            $this->compressFile($sql_file, $final_file);
            if (file_exists($final_file)) {
                unlink($sql_file);
            }
        }

        return basename($final_file);
    }

    public function restoreBackup($filename) {
        if (!empty($this->errors)) {
            throw new Exception(implode("\n", $this->errors));
        }

        $filepath = $this->backup_dir . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception("Arquivo de backup não encontrado!");
        }

        $this->connect();

        // Verificar se é arquivo compactado
        if (pathinfo($filepath, PATHINFO_EXTENSION) === 'gz') {
            $content = gzdecode(file_get_contents($filepath));
        } else {
            $content = file_get_contents($filepath);
        }

        if ($content === false) {
            throw new Exception("Não foi possível ler o arquivo de backup!");
        }

        // Desativar verificação de chaves estrangeiras temporariamente
        $this->conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Executar queries em transação
        $this->conn->begin_transaction();

        try {
            // Dividir o conteúdo em queries individuais
            $queries = $this->splitSQLQueries($content);
            
            foreach ($queries as $query) {
                $query = trim($query);
                
                if (!empty($query) && $query !== ';') {
                    // Modificar CREATE TABLE para CREATE TABLE IF NOT EXISTS
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        $query = str_ireplace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $query);
                    }
                    
                    // Para INSERT, limpar a tabela específica antes de inserir
                    if (stripos($query, 'INSERT INTO') === 0) {
                        $tableName = $this->extractTableNameFromInsert($query);
                        if ($tableName) {
                            // Limpar apenas os dados da tabela específica
                            $this->conn->query("DELETE FROM $tableName");
                        }
                    }
                    
                    // Executar a query
                    if ($this->conn->query($query) === false) {
                        // Ignorar erros de "tabela já existe" e continuar
                        if (stripos($this->conn->error, 'already exists') === false) {
                            throw new Exception("Erro na query: " . $this->conn->error . "\nQuery: " . substr($query, 0, 100) . "...");
                        }
                    }
                }
            }

            $this->conn->commit();
            $this->conn->query("SET FOREIGN_KEY_CHECKS = 1");

            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->conn->query("SET FOREIGN_KEY_CHECKS = 1");
            throw new Exception("Falha na restauração: " . $e->getMessage());
        }
    }

    private function extractTableNameFromInsert($insertQuery) {
        // Extrai o nome da tabela de uma query INSERT
        if (preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $insertQuery, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function splitSQLQueries($sql) {
        // Dividir queries por ponto e vírgula, ignorando dentro de strings
        $queries = [];
        $current = '';
        $in_string = false;
        $string_char = '';
        $escaped = false;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if (($char === "'" || $char === '"') && !$in_string) {
                $in_string = true;
                $string_char = $char;
                $current .= $char;
            } elseif ($char === $string_char && $in_string) {
                $in_string = false;
                $string_char = '';
                $current .= $char;
            } elseif ($char === ';' && !$in_string) {
                $queries[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty(trim($current))) {
            $queries[] = $current;
        }

        return $queries;
    }

    private function generateBackup() {
        $output = "-- MySQL Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: " . DB_NAME . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Backup das tabelas
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $output .= $this->getTableStructure($table);
            $output .= $this->getTableData($table);
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $output;
    }

    private function getTables() {
        $tables = array();
        $result = $this->conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    private function getTableStructure($table) {
        $output = "\n--\n-- Estrutura da tabela `$table`\n--\n";
        $result = $this->conn->query("SHOW CREATE TABLE $table");
        $row = $result->fetch_row();
        $output .= $row[1] . ";\n\n";
        return $output;
    }

    private function getTableData($table) {
        $output = "--\n-- Dados da tabela `$table`\n--\n";
        $result = $this->conn->query("SELECT * FROM $table");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $values = array();
                foreach ($row as $value) {
                    $values[] = is_null($value) ? 'NULL' : "'" . $this->conn->real_escape_string($value) . "'";
                }
                $output .= "INSERT INTO $table VALUES (" . implode(", ", $values) . ");\n";
            }
            $output .= "\n";
        }
        return $output;
    }

    private function compressFile($source, $destination) {
        $data = file_get_contents($source);
        $gzdata = gzencode($data, 9);
        file_put_contents($destination, $gzdata);
    }

    public function listBackups() {
        $files = glob($this->backup_dir . '*.{sql,sql.gz}', GLOB_BRACE);
        $backups = array();
        
        foreach ($files as $file) {
            $backups[] = array(
                'name' => basename($file),
                'size' => filesize($file),
                'date' => filemtime($file),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            );
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $backups;
    }

    public function downloadBackup($filename) {
        $filepath = $this->backup_dir . $filename;
        
        if (file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }

    public function deleteBackup($filename) {
        $filepath = $this->backup_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}

// Processar ações APENAS se o formulário for submetido
$backup = new MySQLBackup();
$message = '';
$permission_errors = $backup->getErrors();

// VERIFICAÇÃO CRÍTICA: Só processa ações se o formulário for submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_backup':
                $filename = $backup->createBackup();
                $message = "✅ Backup criado com sucesso: $filename";
                break;
                
            case 'delete_backup':
                if (isset($_POST['filename'])) {
                    if ($backup->deleteBackup($_POST['filename'])) {
                        $message = "✅ Backup excluído com sucesso!";
                    } else {
                        $message = "❌ Erro ao excluir backup!";
                    }
                }
                break;
                
            case 'restore_backup':
                if (isset($_POST['filename'])) {
                    if ($backup->restoreBackup($_POST['filename'])) {
                        $message = "✅ Backup restaurado com sucesso!";
                    } else {
                        $message = "❌ Erro ao restaurar backup!";
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = "❌ Erro: " . $e->getMessage();
    }
}

// Download é via GET mas só processa se os parâmetros estiverem corretos
if (isset($_GET['action']) && $_GET['action'] == 'download' && isset($_GET['file'])) {
    $backup->downloadBackup($_GET['file']);
    exit;
}

$backups = $backup->listBackups();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup MySQL - Interface Web</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        h1 {
            font-size: 3em;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .subtitle {
            font-size: 1.4em;
            opacity: 0.95;
            font-weight: 300;
        }

        .main-content {
            padding: 40px;
        }

        .card {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #eaeaea;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #00b894 0%, #00a382 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 18px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }

        th {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
            transition: transform 0.2s ease;
        }

        .alert {
            padding: 20px;
            margin: 25px 0;
            border-radius: 12px;
            border-left: 5px solid;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-color: #f44336;
            color: #c62828;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border-color: #ff9800;
            color: #e65100;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-color: #2196f3;
            color: #1565c0;
        }

        .file-size {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .backup-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 25px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
        }

        .stats {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            min-width: 140px;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .config-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 25px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid #2196f3;
        }

        .config-info p {
            margin: 8px 0;
            font-family: 'Courier New', monospace;
            font-size: 15px;
            color: #1976d2;
        }

        .warning-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            font-size: 16px;
            color: #856404;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                margin: 15px;
                border-radius: 15px;
            }
            
            header {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2.2em;
            }
            
            .subtitle {
                font-size: 1.1em;
            }
            
            .main-content {
                padding: 25px;
            }
            
            .backup-info {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 8px;
                padding: 12px 20px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 12px 8px;
            }
            
            .stat-item {
                min-width: 120px;
                padding: 15px;
            }
            
            .stat-number {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📦 Backup MySQL</h1>
            <p class="subtitle">Sistema Completo de Backup e Restauração</p>
        </header>

        <div class="main-content">
            <?php if (!empty($permission_errors)): ?>
                <div class="alert alert-danger">
                    <h3>⚠️ Problemas de Permissão Detectados</h3>
                    <?php foreach ($permission_errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert <?php 
                    if (strpos($message, '✅') !== false) echo 'alert-success';
                    elseif (strpos($message, '❌') !== false) echo 'alert-danger';
                    else echo 'alert-info';
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="backup-info">
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($backups); ?></div>
                        <div>Backups Existentes</div>
                    </div>
                </div>
                
                <form method="POST" onsubmit="return confirm('Deseja criar um novo backup?')">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-success" <?php echo !empty($permission_errors) ? 'disabled' : ''; ?>>
                        🗄️ Criar Novo Backup
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>📋 Backups Existentes</h2>
                
                <?php if (empty($backups)): ?>
                    <p style="text-align: center; padding: 30px; color: #6c757d;">
                        📝 Nenhum backup encontrado. Clique em "Criar Novo Backup" para gerar o primeiro.
                    </p>
                <?php else: ?>
                    <div class="warning-box">
                        <strong>⚠️ ATENÇÃO:</strong> A restauração de backup irá SOBRESCREVER todos os dados atuais do banco. 
                        Esta ação não pode ser desfeita! Faça um backup atual antes de restaurar.
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Nome do Arquivo</th>
                                <th>Tamanho</th>
                                <th>Data de Criação</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup_file): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($backup_file['name']); ?></strong></td>
                                    <td><span class="file-size"><?php echo round($backup_file['size'] / 1024, 2); ?> KB</span></td>
                                    <td><?php echo date('d/m/Y H:i:s', $backup_file['date']); ?></td>
                                    <td><span style="background: #e9ecef; padding: 4px 8px; border-radius: 6px; font-weight: 600;"><?php echo strtoupper($backup_file['type']); ?></span></td>
                                    <td class="actions">
                                        <a href="?action=download&file=<?php echo urlencode($backup_file['name']); ?>" class="btn">
                                            ⬇️ Download
                                        </a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup_file['name']); ?>">
                                            <button type="submit" class="btn btn-warning" onclick="return confirmRestore()">
                                                🔄 Restaurar
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup_file['name']); ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este backup?')">
                                                🗑️ Excluir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>⚙️ Configurações do Banco de Dados</h2>
                <div class="config-info">
                    <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
                    <p><strong>Banco:</strong> <?php echo DB_NAME; ?></p>
                    <p><strong>Usuário:</strong> <?php echo DB_USER; ?></p>
                    <p><strong>Senha:</strong> <?php echo str_repeat('*', strlen(DB_PASS)); ?></p>
                    <p><strong>Diretório de Backups:</strong> <?php echo __DIR__ . '/backups/'; ?></p>
                    <p><strong>Status da Conexão:</strong> 
                        <?php 
                        try {
                            $test_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                            if ($test_conn->connect_error) {
                                throw new Exception("Conexão falhou");
                            }
                            echo "✅ Conectado com sucesso";
                            $test_conn->close();
                        } catch (Exception $e) {
                            echo "❌ Erro de conexão";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmRestore() {
            return confirm('⚠️ ATENÇÃO CRÍTICA!\n\nEsta ação irá SOBRESCREVER COMPLETAMENTE todos os dados atuais do banco.\n\nTem certeza absoluta que deseja restaurar este backup?');
        }

        // Auto-esconder mensagens após 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
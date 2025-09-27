<?php
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

if (isset($_GET['id'])) {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Atualiza o campo IGNORA_GASTO para 'S' (ignorado)
        $stmt = $conn->prepare("UPDATE gastos_nubank SET IGNORA_GASTO = 'S' WHERE NNUMEGASTO = ?");
        $stmt->execute([$_GET['id']]);

        header("Location: gastos_pessoa.php");
        exit();
    } catch(PDOException $e) {
        echo "<h2>Erro ao ignorar o gasto: " . $e->getMessage() . "</h2>";
    }
}
?>

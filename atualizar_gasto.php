<?php
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "UPDATE gastos_nubank SET 
                DREALGASTO = :data,
                CDESCGASTO = :descricao,
                NVALOGASTO = :valor,
                CCATEGASTO = :categoria
                WHERE NNUMEGASTO = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':data' => $_POST['data'],
            ':descricao' => $_POST['descricao'],
            ':valor' => $_POST['valor'],
            ':categoria' => $_POST['categoria'],
            ':id' => $_POST['id']
        ]);

        header("Location: gastos_pessoa.php");
        exit();
    } catch(PDOException $e) {
        echo "<h2>Erro ao atualizar: " . $e->getMessage() . "</h2>";
    }
}
?>
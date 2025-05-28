<?php
// Connessione al database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'magazzino';

$conn = new mysqli($host, $username, $password, $database);

// Controllo connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Recupero tutti i prodotti con le loro scorte
$sql = "SELECT p.*, 
        COALESCE(SUM(l.quantita_attuale), 0) as scorta_totale,
        COALESCE(SUM(l.quantita_attuale * p.prezzo_unitario), 0) as valore_totale
        FROM prodotti p 
        LEFT JOIN lotti l ON p.id = l.prodotto_id 
        GROUP BY p.id
        ORDER BY p.nome";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Magazzino</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">🏪 Gestione Magazzino</h1>
                
                <!-- Pulsanti principali -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <a href="nuovo_prodotto.php" class="btn btn-success btn-lg w-100">
                            ➕ Nuovo Prodotto
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="nuovo_lotto.php" class="btn btn-primary btn-lg w-100">
                            📦 Nuovo Lotto
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="movimento.php" class="btn btn-warning btn-lg w-100">
                            📝 Movimento
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="storico_movimenti.php" class="btn btn-info btn-lg w-100">
                            📊 Storico
                        </a>
                    </div>
                </div>

                <!-- Statistiche veloci -->
                <?php
                $stats_sql = "SELECT 
                    COUNT(*) as totale_prodotti,
                    COALESCE(SUM(l.quantita_attuale * p.prezzo_unitario), 0) as valore_magazzino
                    FROM prodotti p 
                    LEFT JOIN lotti l ON p.id = l.prodotto_id";
                $stats_result = $conn->query($stats_sql);
                $stats = $stats_result->fetch_assoc();
                ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['totale_prodotti']; ?></h4>
                                <p>Prodotti Totali</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4>€ <?php echo number_format($stats['valore_magazzino'], 2, ',', '.'); ?></h4>
                                <p>Valore Magazzino</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabella prodotti -->
                <div class="card">
                    <div class="card-header">
                        <h3>Lista Prodotti</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Codice</th>
                                        <th>Nome</th>
                                        <th>Prezzo Unitario</th>
                                        <th>Scorta</th>
                                        <th>Valore Totale</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['codice']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                        <td>€ <?php echo number_format($row['prezzo_unitario'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['scorta_totale'] < 5 ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $row['scorta_totale']; ?>
                                            </span>
                                        </td>
                                        <td>€ <?php echo number_format($row['valore_totale'], 2, ',', '.'); ?></td>
                                        <td>
                                            <a href="dettaglio_prodotto.php?id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                👁️ Dettagli
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <h4>Nessun prodotto inserito</h4>
                            <p>Inizia creando il tuo primo prodotto!</p>
                            <a href="nuovo_prodotto.php" class="btn btn-success">➕ Crea Primo Prodotto</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
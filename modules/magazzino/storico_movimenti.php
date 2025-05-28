<?php
// Connessione al database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'magazzino';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Filtri
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_prodotto = isset($_GET['prodotto']) ? $_GET['prodotto'] : '';
$filtro_data_da = isset($_GET['data_da']) ? $_GET['data_da'] : '';
$filtro_data_a = isset($_GET['data_a']) ? $_GET['data_a'] : '';

// Costruzione query con filtri
$where_conditions = array();
$params = array();
$types = '';

if (!empty($filtro_tipo)) {
    $where_conditions[] = "m.tipo = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if (!empty($filtro_prodotto)) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filtro_prodotto;
    $types .= 'i';
}

if (!empty($filtro_data_da)) {
    $where_conditions[] = "DATE(m.data_movimento) >= ?";
    $params[] = $filtro_data_da;
    $types .= 's';
}

if (!empty($filtro_data_a)) {
    $where_conditions[] = "DATE(m.data_movimento) <= ?";
    $params[] = $filtro_data_a;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query principale per movimenti
$movimenti_sql = "SELECT m.*, p.codice, p.nome as prodotto_nome, l.fornitore, l.data_arrivo
                  FROM movimenti m
                  JOIN lotti l ON m.lotto_id = l.id
                  JOIN prodotti p ON l.prodotto_id = p.id
                  $where_clause
                  ORDER BY m.data_movimento DESC";

if (!empty($params)) {
    $movimenti_stmt = $conn->prepare($movimenti_sql);
    $movimenti_stmt->bind_param($types, ...$params);
    $movimenti_stmt->execute();
    $movimenti_result = $movimenti_stmt->get_result();
} else {
    $movimenti_result = $conn->query($movimenti_sql);
}

// Query per dropdown prodotti
$prodotti_sql = "SELECT DISTINCT p.id, p.codice, p.nome 
                 FROM prodotti p 
                 JOIN lotti l ON p.id = l.prodotto_id 
                 JOIN movimenti m ON l.id = m.lotto_id 
                 ORDER BY p.nome";
$prodotti_result = $conn->query($prodotti_sql);

// Statistiche veloci
$stats_sql = "SELECT 
                COUNT(*) as totale_movimenti,
                SUM(CASE WHEN tipo = 'entrata' THEN quantita ELSE 0 END) as totale_entrate,
                SUM(CASE WHEN tipo = 'uscita' THEN quantita ELSE 0 END) as totale_uscite
              FROM movimenti m
              $where_clause";

if (!empty($params)) {
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param($types, ...$params);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
} else {
    $stats_result = $conn->query($stats_sql);
}

$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storico Movimenti - Gestione Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📊 Storico Movimenti</h1>
            <a href="index.php" class="btn btn-secondary">
                ⬅️ Torna alla Lista
            </a>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['totale_movimenti']; ?></h4>
                        <p>Movimenti Totali</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['totale_entrate']; ?></h4>
                        <p>Pezzi Entrati</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['totale_uscite']; ?></h4>
                        <p>Pezzi Usciti</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>🔍 Filtri di Ricerca</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="tipo" class="form-label">Tipo Movimento</label>
                                <select class="form-select" id="tipo" name="tipo">
                                    <option value="">Tutti i tipi</option>
                                    <option value="entrata" <?php echo $filtro_tipo == 'entrata' ? 'selected' : ''; ?>>
                                        ⬆️ Solo Entrate
                                    </option>
                                    <option value="uscita" <?php echo $filtro_tipo == 'uscita' ? 'selected' : ''; ?>>
                                        ⬇️ Solo Uscite
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="prodotto" class="form-label">Prodotto</label>
                                <select class="form-select" id="prodotto" name="prodotto">
                                    <option value="">Tutti i prodotti</option>
                                    <?php while($prodotto = $prodotti_result->fetch_assoc()): ?>
                                    <option value="<?php echo $prodotto['id']; ?>" 
                                            <?php echo $filtro_prodotto == $prodotto['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prodotto['codice'] . ' - ' . $prodotto['nome']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="data_da" class="form-label">Data Da</label>
                                <input type="date" class="form-control" id="data_da" name="data_da" 
                                       value="<?php echo htmlspecialchars($filtro_data_da); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="data_a" class="form-label">Data A</label>
                                <input type="date" class="form-control" id="data_a" name="data_a" 
                                       value="<?php echo htmlspecialchars($filtro_data_a); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            🔍 Applica Filtri
                        </button>
                        <a href="storico_movimenti.php" class="btn btn-outline-secondary">
                            🗑️ Pulisci Filtri
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella movimenti -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Lista Movimenti</h5>
                <small class="text-muted">
                    <?php echo $movimenti_result->num_rows; ?> movimenti trovati
                </small>
            </div>
            <div class="card-body">
                <?php if ($movimenti_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Data/Ora</th>
                                <th>Tipo</th>
                                <th>Prodotto</th>
                                <th>Fornitore/Lotto</th>
                                <th>Quantità</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($movimento = $movimenti_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo date('d/m/Y', strtotime($movimento['data_movimento'])); ?><br>
                                        <span class="text-muted"><?php echo date('H:i', strtotime($movimento['data_movimento'])); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($movimento['tipo'] == 'entrata'): ?>
                                        <span class="badge bg-success">⬆️ Entrata</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">⬇️ Uscita</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($movimento['codice']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($movimento['prodotto_nome']); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <strong>Forn:</strong> <?php echo htmlspecialchars($movimento['fornitore']); ?><br>
                                        <span class="text-muted">
                                            Lotto: <?php echo date('d/m/Y', strtotime($movimento['data_arrivo'])); ?>
                                        </span>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $movimento['tipo'] == 'entrata' ? 'bg-success' : 'bg-danger'; ?> fs-6">
                                        <?php echo $movimento['tipo'] == 'entrata' ? '+' : '-'; ?><?php echo $movimento['quantita']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($movimento['note'] ?: 'Nessuna nota'); ?></small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <h4>📭 Nessun movimento trovato</h4>
                    <p>Non ci sono movimenti che corrispondono ai filtri selezionati.</p>
                    <?php if (!empty(array_filter([$filtro_tipo, $filtro_prodotto, $filtro_data_da, $filtro_data_a]))): ?>
                    <a href="storico_movimenti.php" class="btn btn-primary">Mostra Tutti i Movimenti</a>
                    <?php else: ?>
                    <p>Inizia registrando alcuni movimenti dal menu principale!</p>
                    <a href="movimento.php" class="btn btn-warning">📝 Registra Movimento</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Link rapidi -->
        <div class="text-center mt-4">
            <a href="movimento.php" class="btn btn-warning me-2">📝 Nuovo Movimento</a>
            <a href="nuovo_lotto.php" class="btn btn-primary me-2">📦 Nuovo Lotto</a>
            <a href="index.php" class="btn btn-success">🏠 Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
<?php
// Configurazione database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "magazzino";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Recupera ID prodotto
$prodotto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($prodotto_id <= 0) {
    header("Location: index.php");
    exit;
}

// Query prodotto principale
$sql_prodotto = "SELECT * FROM prodotti WHERE id = ?";
$stmt_prodotto = $conn->prepare($sql_prodotto);
$stmt_prodotto->bind_param("i", $prodotto_id);
$stmt_prodotto->execute();
$result_prodotto = $stmt_prodotto->get_result();

if ($result_prodotto->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$prodotto = $result_prodotto->fetch_assoc();

// Query lotti per questo prodotto
$sql_lotti = "SELECT l.*, 
              (l.quantita_iniziale - l.quantita_attuale) as quantita_utilizzata,
              CASE 
                WHEN l.quantita_attuale > 0 THEN 'Disponibile'
                ELSE 'Esaurito'
              END as stato
              FROM lotti l 
              WHERE l.prodotto_id = ?
              ORDER BY l.data_arrivo DESC";
$stmt_lotti = $conn->prepare($sql_lotti);
$stmt_lotti->bind_param("i", $prodotto_id);
$stmt_lotti->execute();
$result_lotti = $stmt_lotti->get_result();

// Query movimenti recenti (ultimi 20)
$sql_movimenti = "SELECT m.*, l.fornitore, l.data_arrivo
                  FROM movimenti m
                  JOIN lotti l ON m.lotto_id = l.id
                  WHERE l.prodotto_id = ?
                  ORDER BY m.data_movimento DESC
                  LIMIT 20";
$stmt_movimenti = $conn->prepare($sql_movimenti);
$stmt_movimenti->bind_param("i", $prodotto_id);
$stmt_movimenti->execute();
$result_movimenti = $stmt_movimenti->get_result();

// Statistiche generali
$sql_stats = "SELECT 
              SUM(l.quantita_attuale) as scorta_totale,
              SUM(l.quantita_attuale * p.prezzo_unitario) as valore_totale,
              COUNT(l.id) as num_lotti,
              SUM(CASE WHEN l.quantita_attuale > 0 THEN 1 ELSE 0 END) as lotti_disponibili,
              MAX(m.data_movimento) as ultimo_movimento
              FROM lotti l
              JOIN prodotti p ON l.prodotto_id = p.id
              LEFT JOIN movimenti m ON l.id = m.lotto_id
              WHERE l.prodotto_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $prodotto_id);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Fornitori abituali
$sql_fornitori = "SELECT fornitore, 
                  COUNT(*) as num_lotti,
                  SUM(quantita_iniziale) as quantita_totale
                  FROM lotti 
                  WHERE prodotto_id = ? 
                  GROUP BY fornitore 
                  ORDER BY num_lotti DESC";
$stmt_fornitori = $conn->prepare($sql_fornitori);
$stmt_fornitori->bind_param("i", $prodotto_id);
$stmt_fornitori->execute();
$result_fornitori = $stmt_fornitori->get_result();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio: <?php echo htmlspecialchars($prodotto['nome']); ?> - Gestionale Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-info text-white p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-box-open"></i> <?php echo htmlspecialchars($prodotto['nome']); ?></h1>
                <p class="mb-0">Codice: <strong><?php echo htmlspecialchars($prodotto['codice']); ?></strong></p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Materiali</li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($prodotto['nome']); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Statistiche Principali -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h4><?php echo $stats['scorta_totale'] ?? 0; ?></h4>
                        <p class="mb-0">Scorta Totale</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h4>€ <?php echo number_format($stats['valore_totale'] ?? 0, 2, ',', '.'); ?></h4>
                        <p class="mb-0">Valore Inventario</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h4><?php echo $stats['lotti_disponibili'] ?? 0; ?>/<?php echo $stats['num_lotti'] ?? 0; ?></h4>
                        <p class="mb-0">Lotti Disponibili</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h4>€ <?php echo number_format($prodotto['prezzo_unitario'], 2, ',', '.'); ?></h4>
                        <p class="mb-0">Prezzo Unitario</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Prodotto + Azioni -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Informazioni Generali</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nome:</strong> <?php echo htmlspecialchars($prodotto['nome']); ?></p>
                                <p><strong>Codice:</strong> <?php echo htmlspecialchars($prodotto['codice']); ?></p>
                                <p><strong>Prezzo Unitario:</strong> € <?php echo number_format($prodotto['prezzo_unitario'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Ultimo Movimento:</strong> 
                                   <?php echo $stats['ultimo_movimento'] ? date('d/m/Y H:i', strtotime($stats['ultimo_movimento'])) : 'Nessuno'; ?>
                                </p>
                                <p><strong>Stato:</strong> 
                                   <span class="badge <?php echo ($stats['scorta_totale'] ?? 0) > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                       <?php echo ($stats['scorta_totale'] ?? 0) > 0 ? 'Disponibile' : 'Esaurito'; ?>
                                   </span>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($prodotto['descrizione'])): ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Descrizione:</strong></p>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($prodotto['descrizione'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Azioni Rapide</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="nuovo_lotto.php?prodotto_id=<?php echo $prodotto['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Nuovo Lotto
                            </a>
                            <a href="movimento.php?prodotto_id=<?php echo $prodotto['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-exchange-alt"></i> Registra Movimento
                            </a>
                            <a href="storico_movimenti.php?prodotto=<?php echo $prodotto['id']; ?>" class="btn btn-info">
                                <i class="fas fa-history"></i> Storico Completo
                            </a>
                            <hr>
                            <a href="nuovo_prodotto.php?edit=<?php echo $prodotto['id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-edit"></i> Modifica Prodotto
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fornitori Abituali -->
        <?php if ($result_fornitori->num_rows > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-truck"></i> Fornitori Abituali</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php while($fornitore = $result_fornitori->fetch_assoc()): ?>
                            <div class="col-md-4 mb-2">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-2">
                                        <h6><?php echo htmlspecialchars($fornitore['fornitore']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $fornitore['num_lotti']; ?> lotti - 
                                            Tot: <?php echo $fornitore['quantita_totale']; ?> pz
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabella Lotti -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-boxes"></i> Tutti i Lotti</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result_lotti->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Data Arrivo</th>
                                        <th>Fornitore</th>
                                        <th>Qtà Iniziale</th>
                                        <th>Qtà Attuale</th>
                                        <th>Utilizzata</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($lotto = $result_lotti->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($lotto['data_arrivo'])); ?></td>
                                        <td><?php echo htmlspecialchars($lotto['fornitore']); ?></td>
                                        <td><?php echo $lotto['quantita_iniziale']; ?></td>
                                        <td><strong><?php echo $lotto['quantita_attuale']; ?></strong></td>
                                        <td><?php echo $lotto['quantita_utilizzata']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $lotto['quantita_attuale'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $lotto['stato']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($lotto['quantita_attuale'] > 0): ?>
                                            <a href="movimento.php?lotto_id=<?php echo $lotto['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Nessun lotto registrato per questo prodotto.
                            <br><br>
                            <a href="nuovo_lotto.php?prodotto_id=<?php echo $prodotto['id']; ?>" class="btn btn-primary">
                                Crea Primo Lotto
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Movimenti Recenti -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Movimenti Recenti</h5>
                        <a href="storico_movimenti.php?prodotto=<?php echo $prodotto['id']; ?>" class="btn btn-sm btn-outline-primary">
                            Vedi Tutti
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($result_movimenti->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Quantità</th>
                                        <th>Lotto (Fornitore)</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($movimento = $result_movimenti->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($movimento['data_movimento'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $movimento['tipo'] == 'entrata' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $movimento['tipo'] == 'entrata' ? '⬆️' : '⬇️'; ?>
                                                <?php echo ucfirst($movimento['tipo']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $movimento['quantita']; ?></strong></td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($movimento['fornitore']); ?>
                                                <br><span class="text-muted"><?php echo date('d/m/Y', strtotime($movimento['data_arrivo'])); ?></span>
                                            </small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($movimento['note'] ?? ''); ?></small></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Nessun movimento registrato per questo prodotto.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Azioni Finali -->
        <div class="row mb-4">
            <div class="col text-center">
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                </a>
                <a href="nuovo_lotto.php?prodotto_id=<?php echo $prodotto['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Lotto
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
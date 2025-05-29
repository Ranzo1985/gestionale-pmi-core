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

$messaggio = '';
$tipo_messaggio = '';

// Recupera ID lotto
$lotto_id = isset($_GET['lotto_id']) ? intval($_GET['lotto_id']) : 0;

if ($lotto_id <= 0) {
    header("Location: anagrafica_lotti.php");
    exit;
}

// Query info lotto
$sql_lotto = "SELECT ll.*, p.codice as prodotto_codice, p.nome as prodotto_nome, 
              p.unita_misura_principale, p.unita_misura_secondaria_1, 
              p.unita_misura_secondaria_2, p.unita_misura_secondaria_3,
              p.conversione_ump_um1, p.conversione_ump_um2, p.conversione_ump_um3,
              f.nome as fornitore_principale_nome
              FROM lotti_logistici ll
              JOIN prodotti p ON ll.prodotto_id = p.id
              LEFT JOIN fornitori f ON ll.fornitore_principale_id = f.id
              WHERE ll.id = ?";
$stmt_lotto = $conn->prepare($sql_lotto);
$stmt_lotto->bind_param("i", $lotto_id);
$stmt_lotto->execute();
$result_lotto = $stmt_lotto->get_result();

if ($result_lotto->num_rows == 0) {
    header("Location: anagrafica_lotti.php");
    exit;
}

$lotto = $result_lotto->fetch_assoc();

// Gestione form inserimento/modifica fornitore
if ($_POST) {
    $azione = $_POST['azione'] ?? 'inserisci';
    $fornitore_lotto_id = isset($_POST['fornitore_lotto_id']) ? intval($_POST['fornitore_lotto_id']) : 0;
    $fornitore_id = intval($_POST['fornitore_id']);
    $tipo_fornitura = $_POST['tipo_fornitura'];
    $unita_misura = $_POST['unita_misura_utilizzata'];
    $quantita = floatval($_POST['quantita_contrattuale']);
    $prezzo = floatval($_POST['prezzo_unitario']);
    $note = trim($_POST['note_fornitura']);
    
    if ($fornitore_id <= 0 || empty($tipo_fornitura) || empty($unita_misura) || $quantita <= 0 || $prezzo <= 0) {
        $messaggio = 'Compila tutti i campi obbligatori con valori validi!';
        $tipo_messaggio = 'danger';
    } else {
        try {
            if ($azione == 'inserisci') {
                // Controllo: solo un materiale per lotto
                if ($tipo_fornitura == 'materiale') {
                    $check_sql = "SELECT COUNT(*) as count FROM lotto_fornitori WHERE lotto_id = ? AND tipo_fornitura = 'materiale'";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("i", $lotto_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $check_data = $check_result->fetch_assoc();
                    
                    if ($check_data['count'] > 0) {
                        throw new Exception("Esiste già un fornitore materiale per questo lotto!");
                    }
                }
                
                // Controllo: max 5 servizi
                if (strpos($tipo_fornitura, 'servizio') !== false) {
                    $check_servizi_sql = "SELECT COUNT(*) as count FROM lotto_fornitori WHERE lotto_id = ? AND tipo_fornitura LIKE 'servizio%'";
                    $check_servizi_stmt = $conn->prepare($check_servizi_sql);
                    $check_servizi_stmt->bind_param("i", $lotto_id);
                    $check_servizi_stmt->execute();
                    $check_servizi_result = $check_servizi_stmt->get_result();
                    $check_servizi_data = $check_servizi_result->fetch_assoc();
                    
                    if ($check_servizi_data['count'] >= 5) {
                        throw new Exception("Massimo 5 servizi per lotto!");
                    }
                }
                
                $sql = "INSERT INTO lotto_fornitori (lotto_id, fornitore_id, tipo_fornitura, unita_misura_utilizzata, quantita_contrattuale, prezzo_unitario, note_fornitura) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissdds", $lotto_id, $fornitore_id, $tipo_fornitura, $unita_misura, $quantita, $prezzo, $note);
                
                if ($stmt->execute()) {
                    $messaggio = 'Fornitore aggiunto con successo!';
                    $tipo_messaggio = 'success';
                    $_POST = array();
                }
            } else {
                // Modifica
                $sql = "UPDATE lotto_fornitori SET fornitore_id = ?, tipo_fornitura = ?, unita_misura_utilizzata = ?, quantita_contrattuale = ?, prezzo_unitario = ?, note_fornitura = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issdsi", $fornitore_id, $tipo_fornitura, $unita_misura, $quantita, $prezzo, $note, $fornitore_lotto_id);
                
                if ($stmt->execute()) {
                    $messaggio = 'Fornitore aggiornato con successo!';
                    $tipo_messaggio = 'success';
                }
            }
        } catch (Exception $e) {
            $messaggio = 'Errore: ' . $e->getMessage();
            $tipo_messaggio = 'danger';
        }
    }
}

// Gestione eliminazione
if (isset($_GET['elimina_fornitore'])) {
    $fornitore_lotto_id = intval($_GET['elimina_fornitore']);
    try {
        $sql_delete = "DELETE FROM lotto_fornitori WHERE id = ? AND lotto_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $fornitore_lotto_id, $lotto_id);
        
        if ($stmt_delete->execute()) {
            $messaggio = 'Fornitore rimosso dal lotto!';
            $tipo_messaggio = 'success';
        }
    } catch (Exception $e) {
        $messaggio = 'Errore eliminazione: ' . $e->getMessage();
        $tipo_messaggio = 'danger';
    }
}

// Recupera fornitori del lotto
$sql_fornitori_lotto = "SELECT lf.*, f.nome as fornitore_nome
                        FROM lotto_fornitori lf
                        JOIN fornitori f ON lf.fornitore_id = f.id
                        WHERE lf.lotto_id = ?
                        ORDER BY 
                            CASE lf.tipo_fornitura 
                                WHEN 'materiale' THEN 1 
                                ELSE 2 
                            END, 
                            lf.tipo_fornitura";
$stmt_fornitori_lotto = $conn->prepare($sql_fornitori_lotto);
$stmt_fornitori_lotto->bind_param("i", $lotto_id);
$stmt_fornitori_lotto->execute();
$result_fornitori_lotto = $stmt_fornitori_lotto->get_result();

// Calcoli totali e per UMP
$costo_totale = 0;
$quantita_materiale_ump = 0;
$costo_per_ump = 0;

while ($fornitore_row = $result_fornitori_lotto->fetch_assoc()) {
    $costo_totale += $fornitore_row['costo_totale'];
    
    // Se è materiale, prendi la quantità per calcolo UMP
    if ($fornitore_row['tipo_fornitura'] == 'materiale' && 
        $fornitore_row['unita_misura_utilizzata'] == $lotto['unita_misura_principale']) {
        $quantita_materiale_ump = $fornitore_row['quantita_contrattuale'];
    }
}

if ($quantita_materiale_ump > 0) {
    $costo_per_ump = $costo_totale / $quantita_materiale_ump;
}

// Reset pointer per visualizzazione
$result_fornitori_lotto->data_seek(0);

// Query tutti i fornitori per dropdown
$sql_fornitori = "SELECT id, nome, tipo FROM fornitori WHERE attivo = 1 ORDER BY tipo, nome";
$result_fornitori = $conn->query($sql_fornitori);

// Array unità di misura disponibili per il prodotto
$unita_misura_disponibili = array();
if (!empty($lotto['unita_misura_principale'])) {
    $unita_misura_disponibili[$lotto['unita_misura_principale']] = 'UMP - ' . $lotto['unita_misura_principale'];
}
if (!empty($lotto['unita_misura_secondaria_1'])) {
    $unita_misura_disponibili[$lotto['unita_misura_secondaria_1']] = 'UM1 - ' . $lotto['unita_misura_secondaria_1'];
}
if (!empty($lotto['unita_misura_secondaria_2'])) {
    $unita_misura_disponibili[$lotto['unita_misura_secondaria_2']] = 'UM2 - ' . $lotto['unita_misura_secondaria_2'];
}
if (!empty($lotto['unita_misura_secondaria_3'])) {
    $unita_misura_disponibili[$lotto['unita_misura_secondaria_3']] = 'UM3 - ' . $lotto['unita_misura_secondaria_3'];
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fornitori Lotto <?php echo htmlspecialchars($lotto['codice_lotto']); ?> - Gestionale PMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-warning text-dark p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-truck-loading"></i> Gestione Fornitori - Lotto <?php echo htmlspecialchars($lotto['codice_lotto']); ?></h1>
                <p class="mb-0">
                    <strong>Prodotto:</strong> <?php echo htmlspecialchars($lotto['prodotto_codice'] . ' - ' . $lotto['prodotto_nome']); ?> |
                    <strong>UMP:</strong> <?php echo htmlspecialchars($lotto['unita_misura_principale']); ?>
                </p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="anagrafica_lotti.php">Lotti Logistici</a></li>
                        <li class="breadcrumb-item active">Fornitori Lotto <?php echo htmlspecialchars($lotto['codice_lotto']); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Messaggio feedback -->
        <?php if (!empty($messaggio)): ?>
        <div class="alert alert-<?php echo $tipo_messaggio; ?> alert-dismissible fade show" role="alert">
            <?php echo $messaggio; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistiche lotto -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white text-center">
                    <div class="card-body">
                        <h4>€ <?php echo number_format($costo_totale, 2, ',', '.'); ?></h4>
                        <p class="mb-0">Costo Totale Lotto</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $quantita_materiale_ump > 0 ? number_format($quantita_materiale_ump, 2, ',', '.') : '0'; ?></h4>
                        <p class="mb-0">Quantità UMP (<?php echo htmlspecialchars($lotto['unita_misura_principale']); ?>)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white text-center">
                    <div class="card-body">
                        <h4>€ <?php echo $costo_per_ump > 0 ? number_format($costo_per_ump, 2, ',', '.') : '0,00'; ?></h4>
                        <p class="mb-0">Costo per <?php echo htmlspecialchars($lotto['unita_misura_principale']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Form aggiunta fornitore -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus"></i> Aggiungi Fornitore al Lotto</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="azione" value="inserisci">

                            <div class="mb-3">
                                <label for="tipo_fornitura" class="form-label">
                                    Tipo Fornitura <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="tipo_fornitura" name="tipo_fornitura" required>
                                    <option value="">Seleziona tipo...</option>
                                    <option value="materiale">🔸 Materiale Base</option>
                                    <option value="servizio_1">🔧 Servizio 1</option>
                                    <option value="servizio_2">🚚 Servizio 2</option>
                                    <option value="servizio_3">⚙️ Servizio 3</option>
                                    <option value="servizio_4">🏭 Servizio 4</option>
                                    <option value="servizio_5">📦 Servizio 5</option>
                                </select>
                                <div class="form-text">Solo 1 materiale e max 5 servizi per lotto</div>
                            </div>

                            <div class="mb-3">
                                <label for="fornitore_id" class="form-label">
                                    Fornitore <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="fornitore_id" name="fornitore_id" required>
                                    <option value="">Seleziona fornitore...</option>
                                    <?php while($fornitore = $result_fornitori->fetch_assoc()): ?>
                                    <option value="<?php echo $fornitore['id']; ?>">
                                        <?php echo htmlspecialchars($fornitore['nome'] . ' (' . $fornitore['tipo'] . ')'); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="unita_misura_utilizzata" class="form-label">
                                            Unità di Misura <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="unita_misura_utilizzata" name="unita_misura_utilizzata" required>
                                            <option value="">Seleziona UM...</option>
                                            <?php foreach($unita_misura_disponibili as $codice => $descrizione): ?>
                                            <option value="<?php echo $codice; ?>">
                                                <?php echo htmlspecialchars($descrizione); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="quantita_contrattuale" class="form-label">
                                            Quantità <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="quantita_contrattuale" 
                                               name="quantita_contrattuale" 
                                               step="0.001" 
                                               min="0.001" 
                                               placeholder="0,000"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="prezzo_unitario" class="form-label">
                                    Prezzo Unitario (€) <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="prezzo_unitario" 
                                       name="prezzo_unitario" 
                                       step="0.0001" 
                                       min="0.0001" 
                                       placeholder="0,0000"
                                       required>
                                <div class="form-text" id="costo_totale_preview">Costo totale: € 0,00</div>
                            </div>

                            <div class="mb-3">
                                <label for="note_fornitura" class="form-label">Note Fornitura</label>
                                <textarea class="form-control" 
                                          id="note_fornitura" 
                                          name="note_fornitura" 
                                          rows="2"
                                          placeholder="Note specifiche per questa fornitura..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-plus"></i> Aggiungi Fornitore
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista fornitori del lotto -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Fornitori del Lotto</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result_fornitori_lotto->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Fornitore</th>
                                        <th>UM</th>
                                        <th>Qtà</th>
                                        <th>€/Unità</th>
                                        <th>Totale</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($fornitore_lotto = $result_fornitori_lotto->fetch_assoc()): ?>
                                    <tr class="<?php echo $fornitore_lotto['tipo_fornitura'] == 'materiale' ? 'table-success' : ''; ?>">
                                        <td>
                                            <small>
                                                <?php if ($fornitore_lotto['tipo_fornitura'] == 'materiale'): ?>
                                                    <span class="badge bg-success">🔸 Materiale</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $fornitore_lotto['tipo_fornitura'])); ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><strong><?php echo htmlspecialchars($fornitore_lotto['fornitore_nome']); ?></strong></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($fornitore_lotto['unita_misura_utilizzata']); ?></td>
                                        <td><?php echo number_format($fornitore_lotto['quantita_contrattuale'], 3, ',', '.'); ?></td>
                                        <td>€ <?php echo number_format($fornitore_lotto['prezzo_unitario'], 4, ',', '.'); ?></td>
                                        <td><strong>€ <?php echo number_format($fornitore_lotto['costo_totale'], 2, ',', '.'); ?></strong></td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <a href="?lotto_id=<?php echo $lotto_id; ?>&elimina_fornitore=<?php echo $fornitore_lotto['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Rimuovere questo fornitore dal lotto?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="5" class="text-end">TOTALE LOTTO:</th>
                                        <th>€ <?php echo number_format($costo_totale, 2, ',', '.'); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Nessun fornitore associato a questo lotto.
                            <br><br>Inizia aggiungendo il fornitore del materiale base!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Azioni finali -->
        <div class="row mt-4">
            <div class="col text-center">
                <a href="anagrafica_lotti.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Torna ai Lotti
                </a>
                <a href="index.php" class="btn btn-success">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Calcolo automatico costo totale
    function aggiornaCalcoloTotale() {
        const quantita = parseFloat(document.getElementById('quantita_contrattuale').value) || 0;
        const prezzo = parseFloat(document.getElementById('prezzo_unitario').value) || 0;
        const totale = quantita * prezzo;
        
        document.getElementById('costo_totale_preview').textContent = 
            'Costo totale: € ' + totale.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    document.getElementById('quantita_contrattuale').addEventListener('input', aggiornaCalcoloTotale);
    document.getElementById('prezzo_unitario').addEventListener('input', aggiornaCalcoloTotale);
    </script>
</body>
</html>

<?php
$conn->close();
?>
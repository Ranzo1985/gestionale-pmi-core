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

// Gestione form inserimento/modifica
if ($_POST) {
    $azione = $_POST['azione'] ?? 'inserisci';
    $lotto_id = isset($_POST['lotto_id']) ? intval($_POST['lotto_id']) : 0;
    $prodotto_id = intval($_POST['prodotto_id']);
    $fornitore_id = intval($_POST['fornitore_principale_id']);
    $descrizione = trim($_POST['descrizione_lotto']);
    $data_arrivo_prevista = $_POST['data_arrivo_prevista'] ?: null;
    $note = trim($_POST['note_generali']);
    
    if ($prodotto_id <= 0 || $fornitore_id <= 0 || empty($descrizione)) {
        $messaggio = 'Compila tutti i campi obbligatori!';
        $tipo_messaggio = 'danger';
    } else {
        try {
            if ($azione == 'inserisci') {
                // Inserimento nuovo lotto (codice generato automaticamente)
                $sql = "INSERT INTO lotti_logistici (prodotto_id, fornitore_principale_id, descrizione_lotto, data_arrivo_prevista, note_generali) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisss", $prodotto_id, $fornitore_id, $descrizione, $data_arrivo_prevista, $note);
                
                if ($stmt->execute()) {
                    $nuovo_lotto_id = $conn->insert_id;
                    
                    // Recupera il codice generato automaticamente
                    $sql_codice = "SELECT codice_lotto FROM lotti_logistici WHERE id = ?";
                    $stmt_codice = $conn->prepare($sql_codice);
                    $stmt_codice->bind_param("i", $nuovo_lotto_id);
                    $stmt_codice->execute();
                    $result_codice = $stmt_codice->get_result();
                    $codice_generato = $result_codice->fetch_assoc()['codice_lotto'];
                    
                    $messaggio = "Lotto creato con successo! Codice: <strong>$codice_generato</strong>";
                    $tipo_messaggio = 'success';
                    $_POST = array(); // Reset form
                } else {
                    throw new Exception($conn->error);
                }
            } else {
                // Modifica lotto esistente
                $sql = "UPDATE lotti_logistici SET prodotto_id = ?, fornitore_principale_id = ?, descrizione_lotto = ?, data_arrivo_prevista = ?, note_generali = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisssi", $prodotto_id, $fornitore_id, $descrizione, $data_arrivo_prevista, $note, $lotto_id);
                
                if ($stmt->execute()) {
                    $messaggio = 'Lotto aggiornato con successo!';
                    $tipo_messaggio = 'success';
                } else {
                    throw new Exception($conn->error);
                }
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'idx_fornitore_descrizione_unico') !== false) {
                $messaggio = 'Errore: Questo fornitore ha già un lotto con la stessa descrizione!';
            } else {
                $messaggio = 'Errore: ' . $e->getMessage();
            }
            $tipo_messaggio = 'danger';
        }
    }
}

// Gestione eliminazione
if (isset($_GET['elimina'])) {
    $lotto_id = intval($_GET['elimina']);
    try {
        $sql_delete = "DELETE FROM lotti_logistici WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $lotto_id);
        
        if ($stmt_delete->execute()) {
            $messaggio = 'Lotto eliminato con successo!';
            $tipo_messaggio = 'success';
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $messaggio = 'Errore nell\'eliminazione: ' . $e->getMessage();
        $tipo_messaggio = 'danger';
    }
}

// Recupera dati per modifica
$lotto_modifica = null;
if (isset($_GET['modifica'])) {
    $lotto_id = intval($_GET['modifica']);
    $sql_modifica = "SELECT * FROM lotti_logistici WHERE id = ?";
    $stmt_modifica = $conn->prepare($sql_modifica);
    $stmt_modifica->bind_param("i", $lotto_id);
    $stmt_modifica->execute();
    $result_modifica = $stmt_modifica->get_result();
    $lotto_modifica = $result_modifica->fetch_assoc();
}

// Query lista lotti con dettagli
$sql_lotti = "SELECT ll.*, 
              p.codice as prodotto_codice, p.nome as prodotto_nome,
              f.nome as fornitore_nome,
              (SELECT COUNT(*) FROM lotto_fornitori lf WHERE lf.lotto_id = ll.id) as num_fornitori,
              (SELECT SUM(lf.costo_totale) FROM lotto_fornitori lf WHERE lf.lotto_id = ll.id) as costo_totale
              FROM lotti_logistici ll
              JOIN prodotti p ON ll.prodotto_id = p.id
              JOIN fornitori f ON ll.fornitore_principale_id = f.id
              ORDER BY ll.data_creazione DESC";
$result_lotti = $conn->query($sql_lotti);

// Query prodotti per dropdown
$sql_prodotti = "SELECT id, codice, nome FROM prodotti ORDER BY nome";
$result_prodotti = $conn->query($sql_prodotti);

// Query fornitori per dropdown
$sql_fornitori = "SELECT id, nome FROM fornitori WHERE attivo = 1 ORDER BY nome";
$result_fornitori = $conn->query($sql_fornitori);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anagrafica Lotti Logistici - Gestionale PMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-success text-white p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-layer-group"></i> Anagrafica Lotti Logistici</h1>
                <p class="mb-0">Gestione completa lotti con codici automatici AAAA-XXX</p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Lotti Logistici</li>
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

        <div class="row">
            <!-- Form Inserimento/Modifica -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-plus"></i> 
                            <?php echo $lotto_modifica ? 'Modifica Lotto' : 'Nuovo Lotto'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($lotto_modifica): ?>
                                <input type="hidden" name="azione" value="modifica">
                                <input type="hidden" name="lotto_id" value="<?php echo $lotto_modifica['id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Codice Lotto</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($lotto_modifica['codice_lotto']); ?>" readonly>
                                    <div class="form-text">Il codice non può essere modificato</div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="azione" value="inserisci">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-magic"></i> <strong>Codice automatico:</strong> 
                                    Verrà generato formato <?php echo date('Y'); ?>-XXX
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="prodotto_id" class="form-label">
                                    Prodotto <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="prodotto_id" name="prodotto_id" required>
                                    <option value="">Seleziona prodotto...</option>
                                    <?php 
                                    $result_prodotti->data_seek(0);
                                    while($prodotto = $result_prodotti->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $prodotto['id']; ?>" 
                                            <?php echo ($lotto_modifica && $lotto_modifica['prodotto_id'] == $prodotto['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prodotto['codice'] . ' - ' . $prodotto['nome']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="fornitore_principale_id" class="form-label">
                                    Fornitore Principale <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="fornitore_principale_id" name="fornitore_principale_id" required>
                                    <option value="">Seleziona fornitore...</option>
                                    <?php 
                                    $result_fornitori->data_seek(0);
                                    while($fornitore = $result_fornitori->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $fornitore['id']; ?>"
                                            <?php echo ($lotto_modifica && $lotto_modifica['fornitore_principale_id'] == $fornitore['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($fornitore['nome']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="descrizione_lotto" class="form-label">
                                    Descrizione Lotto <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="descrizione_lotto" 
                                       name="descrizione_lotto" 
                                       placeholder="es. Tronchi Larice Boschiva Nord"
                                       value="<?php echo $lotto_modifica ? htmlspecialchars($lotto_modifica['descrizione_lotto']) : ''; ?>"
                                       required>
                                <div class="form-text">Deve essere unica per questo fornitore</div>
                            </div>

                            <div class="mb-3">
                                <label for="data_arrivo_prevista" class="form-label">
                                    Data Arrivo Prevista
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="data_arrivo_prevista" 
                                       name="data_arrivo_prevista"
                                       value="<?php echo $lotto_modifica ? $lotto_modifica['data_arrivo_prevista'] : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="note_generali" class="form-label">Note Generali</label>
                                <textarea class="form-control" 
                                          id="note_generali" 
                                          name="note_generali" 
                                          rows="3"
                                          placeholder="Note aggiuntive sul lotto..."><?php echo $lotto_modifica ? htmlspecialchars($lotto_modifica['note_generali']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $lotto_modifica ? 'Aggiorna Lotto' : 'Crea Lotto'; ?>
                                </button>
                                <?php if ($lotto_modifica): ?>
                                <a href="anagrafica_lotti.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annulla Modifica
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista Lotti -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Lotti Esistenti</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result_lotti->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Codice</th>
                                        <th>Prodotto</th>
                                        <th>Fornitore</th>
                                        <th>Descrizione</th>
                                        <th>Arrivo Prev.</th>
                                        <th>Stato</th>
                                        <th>Costo Tot.</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($lotto = $result_lotti->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($lotto['codice_lotto']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <strong><?php echo htmlspecialchars($lotto['prodotto_codice']); ?></strong><br>
                                                <?php echo htmlspecialchars($lotto['prodotto_nome']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($lotto['fornitore_nome']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($lotto['descrizione_lotto']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo $lotto['data_arrivo_prevista'] ? date('d/m/Y', strtotime($lotto['data_arrivo_prevista'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $lotto['stato'] == 'arrivato' ? 'success' : 
                                                    ($lotto['stato'] == 'in_transito' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($lotto['stato']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($lotto['costo_totale']): ?>
                                                <strong>€ <?php echo number_format($lotto['costo_totale'], 2, ',', '.'); ?></strong>
                                                <br><small class="text-muted"><?php echo $lotto['num_fornitori']; ?> fornitori</small>
                                            <?php else: ?>
                                                <small class="text-muted">Non configurato</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <a href="gestione_fornitori_lotto.php?lotto_id=<?php echo $lotto['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-truck"></i> Fornitori
                                                </a>
                                                <a href="?modifica=<?php echo $lotto['id']; ?>" 
                                                   class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Modifica
                                                </a>
                                                <a href="?elimina=<?php echo $lotto['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Eliminare questo lotto?')">
                                                    <i class="fas fa-trash"></i> Elimina
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Nessun lotto creato ancora.
                            <br><br>Inizia creando il tuo primo lotto logistico!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link navigazione -->
        <div class="row mt-4">
            <div class="col text-center">
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                </a>
                <a href="gestione_fornitori.php" class="btn btn-info">
                    <i class="fas fa-truck"></i> Gestione Fornitori
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
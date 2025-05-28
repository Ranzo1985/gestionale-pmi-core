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

$messaggio = '';
$tipo_messaggio = '';

// Recupera tutti i prodotti per il dropdown
$prodotti_sql = "SELECT id, codice, nome FROM prodotti ORDER BY nome";
$prodotti_result = $conn->query($prodotti_sql);

// Gestione invio form
if ($_POST) {
    $prodotto_id = intval($_POST['prodotto_id']);
    $data_arrivo = $_POST['data_arrivo'];
    $fornitore = trim($_POST['fornitore']);
    $quantita = intval($_POST['quantita']);
    
    // Validazione
    if ($prodotto_id <= 0 || empty($data_arrivo) || empty($fornitore) || $quantita <= 0) {
        $messaggio = 'Compila tutti i campi con valori validi!';
        $tipo_messaggio = 'danger';
    } else {
        // Inserimento lotto
        $insert_sql = "INSERT INTO lotti (prodotto_id, data_arrivo, fornitore, quantita_iniziale, quantita_attuale) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("issii", $prodotto_id, $data_arrivo, $fornitore, $quantita, $quantita);
        
        if ($insert_stmt->execute()) {
            $messaggio = 'Lotto inserito con successo! Scorte aggiornate.';
            $tipo_messaggio = 'success';
            // Pulisci i campi
            $_POST = array();
        } else {
            $messaggio = 'Errore durante l\'inserimento: ' . $conn->error;
            $tipo_messaggio = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Lotto - Gestione Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>📦 Nuovo Lotto</h1>
                    <a href="index.php" class="btn btn-secondary">
                        ⬅️ Torna alla Lista
                    </a>
                </div>

                <!-- Messaggio di feedback -->
                <?php if (!empty($messaggio)): ?>
                <div class="alert alert-<?php echo $tipo_messaggio; ?> alert-dismissible fade show" role="alert">
                    <?php echo $messaggio; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Controllo se ci sono prodotti -->
                <?php if ($prodotti_result->num_rows == 0): ?>
                <div class="alert alert-warning">
                    <h4>⚠️ Nessun prodotto disponibile</h4>
                    <p>Prima di inserire lotti, devi creare almeno un prodotto.</p>
                    <a href="nuovo_prodotto.php" class="btn btn-success">➕ Crea Primo Prodotto</a>
                </div>
                <?php else: ?>

                <!-- Form inserimento lotto -->
                <div class="card">
                    <div class="card-header">
                        <h3>Arrivo Merce</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prodotto_id" class="form-label">
                                            Prodotto <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="prodotto_id" name="prodotto_id" required>
                                            <option value="">Seleziona un prodotto...</option>
                                            <?php 
                                            $prodotti_result->data_seek(0); // Reset del cursore
                                            while($prodotto = $prodotti_result->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $prodotto['id']; ?>" 
                                                    <?php echo (isset($_POST['prodotto_id']) && $_POST['prodotto_id'] == $prodotto['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prodotto['codice'] . ' - ' . $prodotto['nome']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="data_arrivo" class="form-label">
                                            Data Arrivo <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="data_arrivo" 
                                               name="data_arrivo" 
                                               value="<?php echo isset($_POST['data_arrivo']) ? $_POST['data_arrivo'] : date('Y-m-d'); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fornitore" class="form-label">
                                            Fornitore <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="fornitore" 
                                               name="fornitore" 
                                               placeholder="es. Ferramenta Rossi"
                                               value="<?php echo isset($_POST['fornitore']) ? htmlspecialchars($_POST['fornitore']) : ''; ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="quantita" class="form-label">
                                            Quantità <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="quantita" 
                                               name="quantita" 
                                               min="1" 
                                               placeholder="es. 50"
                                               value="<?php echo isset($_POST['quantita']) ? $_POST['quantita'] : ''; ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">
                                    ❌ Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    💾 Registra Lotto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info box -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>💡 Cosa è un Lotto?</h5>
                    </div>
                    <div class="card-body">
                        <p>Un <strong>lotto</strong> rappresenta un arrivo di merce nel tuo magazzino:</p>
                        <ul class="mb-0">
                            <li><strong>Prodotto:</strong> Cosa è arrivato</li>
                            <li><strong>Data:</strong> Quando è arrivato</li>
                            <li><strong>Fornitore:</strong> Da chi l'hai comprato</li>
                            <li><strong>Quantità:</strong> Quanti pezzi sono arrivati</li>
                        </ul>
                        <hr>
                        <small class="text-muted">
                            <strong>Esempio:</strong> Hai ricevuto 20 martelli dalla "Ferramenta Rossi" il 15/01/2024
                        </small>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
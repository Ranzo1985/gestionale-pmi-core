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

// Gestione invio form
if ($_POST) {
    $codice = trim($_POST['codice']);
    $nome = trim($_POST['nome']);
    $descrizione = trim($_POST['descrizione']);
    $prezzo = floatval($_POST['prezzo']);
    
    // Validazione semplice
    if (empty($codice) || empty($nome) || $prezzo <= 0) {
        $messaggio = 'Compila tutti i campi obbligatori e inserisci un prezzo valido!';
        $tipo_messaggio = 'danger';
    } else {
        // Controllo se il codice esiste già
        $check_sql = "SELECT id FROM prodotti WHERE codice = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $codice);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $messaggio = 'Codice prodotto già esistente! Usa un codice diverso.';
            $tipo_messaggio = 'warning';
        } else {
            // Inserimento prodotto
            $insert_sql = "INSERT INTO prodotti (codice, nome, descrizione, prezzo_unitario) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssd", $codice, $nome, $descrizione, $prezzo);
            
            if ($insert_stmt->execute()) {
                $messaggio = 'Prodotto inserito con successo!';
                $tipo_messaggio = 'success';
                // Pulisci i campi dopo l'inserimento
                $_POST = array();
            } else {
                $messaggio = 'Errore durante l\'inserimento: ' . $conn->error;
                $tipo_messaggio = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Prodotto - Gestione Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>➕ Nuovo Prodotto</h1>
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

                <!-- Form inserimento prodotto -->
                <div class="card">
                    <div class="card-header">
                        <h3>Dati Prodotto</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="codice" class="form-label">
                                            Codice Prodotto <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="codice" 
                                               name="codice" 
                                               placeholder="es. PROD001" 
                                               value="<?php echo isset($_POST['codice']) ? htmlspecialchars($_POST['codice']) : ''; ?>"
                                               required>
                                        <div class="form-text">Codice univoco per identificare il prodotto</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prezzo" class="form-label">
                                            Prezzo Unitario (€) <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="prezzo" 
                                               name="prezzo" 
                                               step="0.01" 
                                               min="0.01" 
                                               placeholder="0.00"
                                               value="<?php echo isset($_POST['prezzo']) ? $_POST['prezzo'] : ''; ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    Nome Prodotto <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nome" 
                                       name="nome" 
                                       placeholder="es. Martello da carpentiere"
                                       value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="descrizione" class="form-label">
                                    Descrizione (opzionale)
                                </label>
                                <textarea class="form-control" 
                                          id="descrizione" 
                                          name="descrizione" 
                                          rows="3" 
                                          placeholder="Descrizione dettagliata del prodotto..."><?php echo isset($_POST['descrizione']) ? htmlspecialchars($_POST['descrizione']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">
                                    ❌ Annulla
                                </a>
                                <button type="submit" class="btn btn-success">
                                    💾 Salva Prodotto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Suggerimenti -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>💡 Suggerimenti</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>Codice Prodotto:</strong> Usa un sistema logico (es. FERN001, FERN002 per i ferramenta)</li>
                            <li><strong>Nome:</strong> Sii specifico per ritrovare facilmente il prodotto</li>
                            <li><strong>Prezzo:</strong> Inserisci il prezzo di vendita o il costo, come preferisci</li>
                            <li><strong>Descrizione:</strong> Aggiungi note utili (fornitore, caratteristiche, ecc.)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
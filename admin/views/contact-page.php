<?php
/**
 * Provide a contact/support view for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h2><span class="dashicons dashicons-money-alt"></span> Support & Licensing PRO - Wasi Sync by Lytta.it</h2>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=lytta-wasi-sync&tab=settings" class="nav-tab">Settings API Base</a>
        <a href="?page=lytta-wasi-sync&tab=contact" class="nav-tab nav-tab-active">Contatti, Supporto e Licenza PRO</a>
    </h2>

    <div style="display:flex; gap: 20px; flex-wrap: wrap; margin-top:20px;">
        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3>🚀 Upgrade a Wasi Sync PRO!</h3>
            <p>La versione gratuita è ottima per testare l'infrastruttura, ma è <strong>limitata all'importazione di soli 10 immobili globali*</strong> e scarica solo l'immagine di anteprima (impedendo di divorare la memoria del server senza garanzia).</p>
            
            <p><strong>Cosa sblocca la versione Premium/PRO:</strong></p>
            <ul style="list-style-type: square; margin-left:20px;">
                <li>Sincronizzazione di un <strong>numero ILLIMITATO</strong> di immobili</li>
                <li>Importazione completa delle <strong>Gallerie Immagini</strong></li>
                <li>Rimozione filtri limite e supporto ad cron intensivi (ogni ora)</li>
                <li>Integrazione universale ACF (Advanced Custom Fields) attiva</li>
            </ul>

            <div style="background: #e5f5fa; border-left: 4px solid #00a0d2; padding: 15px; margin: 20px 0;">
                <h4>Acquista la tua chiave (120€/anno IVA escl.)</h4>
                <p>Scrivici un email per generare la fattura ed ottenere l'Upgrade Token: <strong><a href="mailto:info@lytta.it">info@lytta.it</a></strong></p>
            </div>
            
            <p><small>*L'auto-updater da GitHub continuerà a correggere i bug di sicurezza gratuitamente anche per la versione Free.</small></p>
        </div>

        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3>🛠 Sviluppo Personalizzato</h3>
            <p>Wasi-Sync supporta i framework leader del mercato (Directorist e campi standard generici ACF). Ma il mondo immobiliare è vasto.</p>
            <p>Il tuo sito sfrutta temi complessi all-in-one? Contatta l'agenzia web Lytta.it per richiedere lo script di conversione (Adapter) per il tuo tema specifico:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>Tema Houzez</li>
                <li>Tema WP Residence</li>
                <li>Goya Real Estate</li>
                <li>Listify</li>
            </ul>

            <p style="margin-top:20px">
                <a href="mailto:info@lytta.it?subject=Richiesta Adattatore Wasi Personalizzato" class="button button-primary button-large">
                    Richiedi Preventivo (info@lytta.it)
                </a>
            </p>
        </div>
    </div>
</div>

# 🔧 Changelog Correzioni Bug

## Versione 1.1.1 - 2025-10-15

### 🐛 Bug Corretti

#### 1. **Webhook Handler - Tipo di Ritorno Errato**
- **File**: `includes/class-webhook-handler.php`
- **Fix**: Corretto il tipo di ritorno da `WP_Error` a `WP_REST_Response` per conformità con l'API REST di WordPress
- **Impatto**: I webhook GitHub ora ricevono sempre risposte corrette

#### 2. **Logger - Vulnerabilità SQL**
- **File**: `includes/class-logger.php`
- **Fix**: Refactoring completo della funzione `get_logs()` per usare query parametrizzate correttamente
- **Impatto**: Eliminato potenziale vettore di SQL injection

#### 3. **Admin - Default Auto-Update**
- **File**: `includes/class-admin.php`
- **Fix**: Corretto il default del checkbox auto_update da `true` a `false`
- **Impatto**: Nuove installazioni hanno aggiornamenti manuali come default (più sicuro)

#### 4. **Rate Limiter - Race Condition**
- **File**: `includes/class-rate-limiter.php`
- **Fix**: Preservazione corretta del timeout delle transient quando si incrementa il contatore
- **Impatto**: Il rate limiting ora funziona correttamente, proteggendo da spam webhook

#### 5. **Uninstall - Cleanup Incompleto**
- **File**: `uninstall.php`
- **Fix**: Aggiunta rimozione completa di tutte le opzioni e cron jobs
- **Impatto**: Database pulito dopo disinstallazione, nessun residuo

---

## 📝 File Modificati

1. `includes/class-webhook-handler.php`
2. `includes/class-admin.php`
3. `includes/class-logger.php`
4. `includes/class-rate-limiter.php`
5. `uninstall.php`

---

## ✅ Testing Raccomandato

Dopo aver aggiornato il plugin, verifica:

1. **Webhook**: 
   - Fai un push su GitHub
   - Verifica che il webhook venga ricevuto correttamente
   - Controlla i log per confermare ricezione

2. **Rate Limiting**:
   - Opzionale: Testa inviando multiple richieste webhook
   - Verifica che dopo 60 richieste/ora vengano bloccate

3. **Installazione/Disinstallazione**:
   - Disinstalla e reinstalla il plugin
   - Verifica che le impostazioni vengano ripristinate dal backup
   - Verifica che dopo disinstallazione il DB sia pulito

4. **Aggiornamenti**:
   - Verifica che il default auto_update sia su "OFF"
   - Testa sia modalità manuale che automatica

---

## 🔒 Sicurezza

Tutte le correzioni migliorano la sicurezza del plugin:
- ✅ Query SQL sicure
- ✅ Rate limiting funzionante
- ✅ Default sicuri per nuove installazioni
- ✅ Cleanup completo dei dati sensibili

---

## 📞 Supporto

Se riscontri problemi dopo l'aggiornamento:
1. Controlla i log del plugin (Admin → Git Updater → Log)
2. Verifica che il webhook GitHub sia configurato correttamente
3. Assicurati che i permessi file siano corretti (755 per directory, 644 per file)

---

**Pronto per il deploy! 🚀**

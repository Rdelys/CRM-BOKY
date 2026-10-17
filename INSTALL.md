# CRM simple — Laravel + Blade

Contacts (prospects/clients), import CSV, réception des prospects du siteweb,
campagnes SMS et email avec ciblage par statut/source.

## 1. Installation (dans le projet `crm` de Laragon)

Aucun package à installer : la connexion admin est incluse (sans Breeze).

Copier le contenu du dossier `crm-pack` dans le projet, en respectant les dossiers
(`app/`, `config/`, `database/`, `resources/`, `routes/`).
**Attention** : `routes/web.php` est remplacé par celui du pack.

## 2. Fichier `.env`

```env
APP_URL=http://crm.test
DB_DATABASE=crm

# Important : exécute les envois directement (pas besoin de queue:work)
QUEUE_CONNECTION=sync

# Jeton secret du formulaire du siteweb (mettre une longue valeur aléatoire)
LEAD_API_TOKEN=change-moi-par-un-long-code-secret

# SMS : "log" pour tester (écrit dans storage/logs/laravel.log)
SMS_DRIVER=log
# Quand la passerelle SMS est choisie :
# SMS_DRIVER=http
# SMS_API_URL=https://...
# SMS_API_KEY=...
# SMS_SENDER=NOMCLIENT

# Email (exemple Brevo SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=votre-login-brevo
MAIL_PASSWORD=votre-cle-smtp
MAIL_FROM_ADDRESS=contact@domaine-du-client.com
MAIL_FROM_NAME="Nom du client"
```

Pour tester les emails sans serveur SMTP : `MAIL_MAILER=log`.

## 3. Base de données

```bash
php artisan migrate
php artisan config:clear
```

## 4. Créer l'utilisateur admin

Il n'y a pas d'inscription publique : seul le compte créé ici peut se connecter.

```bash
php artisan tinker
>>> \App\Models\User::create(['name'=>'Admin','email'=>'admin@client.com','password'=>bcrypt('MotDePasseSolide')]);
>>> exit
```

Puis ouvrir `http://127.0.0.1:8000` (après `php artisan serve`) ou `http://crm.test`
et se connecter. D'autres utilisateurs se créent de la même façon.

## 5. Formulaire du siteweb

Coller ce formulaire sur le siteweb du client (WordPress : bloc « HTML personnalisé »).
Remplacer l'URL du CRM, le jeton et l'URL de redirection :

```html
<form method="POST" action="https://crm.domaine-du-client.com/leads">
  <input type="hidden" name="token" value="LE_MEME_JETON_QUE_LEAD_API_TOKEN">
  <input type="hidden" name="source" value="website">
  <input type="hidden" name="redirect" value="https://domaine-du-client.com/merci">
  <input type="text" name="name" placeholder="Nom" required>
  <input type="text" name="phone" placeholder="Téléphone">
  <input type="email" name="email" placeholder="Email">
  <textarea name="message" placeholder="Message"></textarea>
  <button type="submit">Envoyer</button>
</form>
```

Le jeton est visible dans le code de la page : il évite seulement les envois automatiques
au hasard. Pour plus de sécurité, ajouter plus tard un antispam (honeypot ou captcha).

## 6. Facebook

- **Simple** : exporter les leads en CSV depuis Facebook (Lead Ads) puis utiliser
  « Importer CSV » dans le CRM (colonnes : `name,phone,email,source,status,city`, avec `source` = `facebook`).
- **Automatique** : via Zapier ou Make, envoyer chaque nouveau lead Facebook en POST
  vers `https://crm.domaine-du-client.com/leads` avec les champs `token`, `name`, `phone`, `email`, `source=facebook`.

## 7. SMS : brancher la passerelle

Ouvrir `app/Services/SmsService.php` et adapter le bloc « À ADAPTER »
(URL, en-têtes, nom des champs) selon la documentation du fournisseur SMS du client.
Tant que `SMS_DRIVER=log`, rien n'est réellement envoyé.

## Utilisation

1. **Contacts** : ajouter, importer en CSV, rechercher, filtrer, cocher « Ne plus contacter ».
2. **Campagnes** : créer une campagne (SMS ou email), choisir le ciblage, créer le brouillon,
   vérifier le nombre de destinataires, puis cliquer sur **Envoyer**.
3. `{nom}` dans le message est remplacé par le nom du contact.
4. Les contacts désinscrits ou sans téléphone/email (selon le canal) sont exclus automatiquement.

## À prévoir pour plus tard

- Lien de désinscription automatique dans les emails et gestion des réponses « STOP » des SMS
- Passage à une vraie queue (`QUEUE_CONNECTION=database` + `php artisan queue:work`) si les envois deviennent gros
- Statistiques, relances automatiques, plusieurs utilisateurs avec rôles

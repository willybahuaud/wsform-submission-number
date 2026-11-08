# Submission Numbers for WS Form

Un numéro lisible et continu pour chaque soumission [WS Form](https://wsform.com/), au format que tu veux : `DEV-2026-0042`.

## Pourquoi ce plugin

WS Form donne bien un `#submit_id` à chaque soumission. Par contre, c'est l'ID de la ligne en base : il est commun à tous les formulaires, et les brouillons « sauvegarder et reprendre » en consomment aussi. Ton formulaire de devis passe de 112 à 157 sans que personne ne comprenne pourquoi.

La demande revient régulièrement chez WS Form (« Auto Increment Number », « Custom ID Field ») sans réponse pour l'instant. Du coup, ce plugin ajoute un champ qui reçoit un numéro propre à son formulaire, ou partagé entre plusieurs formulaires, pour les bons de commande, devis, tickets, dossiers ou inscriptions.

Il marche avec WS Form LITE et PRO.

## Installation

1. Télécharge `wsform-submission-number-X.Y.Z.zip` depuis la [dernière release](https://github.com/willybahuaud/wsform-submission-number/releases/latest). Pas l'archive du code source : elle contient tout le dépôt.
2. Installe-le comme n'importe quelle extension, à côté de WS Form.
3. Ajoute un champ « Submission Number » (groupe Avancé) à ton formulaire, et publie-le.

Les mises à jour arrivent ensuite dans l'admin, depuis les releases GitHub.

## Ce que ça ajoute

**Le champ « Submission Number »**

- Invisible pour le visiteur, il reçoit le numéro côté serveur au moment où la soumission est enregistrée
- Une valeur envoyée par le navigateur est ignorée
- C'est un vrai champ : le numéro apparaît dans la liste des soumissions, l'export CSV, la recherche, `#field(ID)`, `#email_submission` et le mapping vers un contenu
- La variable `#submission_number` renvoie le numéro du premier champ de ce type, pratique dans l'objet d'un e-mail ou le message de confirmation

**Les séquences, dans WS Form > Numbering**

Un champ ajouté tel quel a sa propre séquence, créée à la publication du formulaire. Pour numéroter plusieurs formulaires à la suite (tous les devis du site, par exemple), crée une séquence partagée et choisis-la dans les réglages du champ.

Chaque séquence a un nom, un format, un nombre de chiffres, une remise à zéro (jamais, chaque année, chaque mois) et un prochain numéro. L'écran montre le dernier numéro donné, le prochain, et les formulaires qui l'utilisent.

**Le format**

| Jeton | Donne |
|---|---|
| `{N}` | le numéro, complété par des zéros selon le réglage « Digits » |
| `{N:6}` | le numéro sur 6 chiffres, quel que soit le réglage |
| `{YYYY}` `{YY}` | l'année du jour où le numéro est donné |
| `{MM}` `{DD}` | le mois et le jour |

Le reste est du texte libre : `DEV-{YYYY}-{N}` donne `DEV-2026-0042`. Un nombre plus long que le remplissage n'est jamais coupé. La date suit le fuseau horaire du site.

Une séquence remise à zéro chaque année doit avoir l'année dans son format, et chaque mois l'année et le mois. Sinon le même numéro reviendrait, donc l'écran refuse d'enregistrer.

**Reprendre la numérotation d'un ancien outil**

Tu remplaces un outil qui en était à 1542 ? Mets 1543 en prochain numéro, l'aperçu te montre tout de suite le résultat formaté.

- Le prochain numéro ne peut jamais redescendre au niveau d'un numéro déjà donné dans la période en cours : il serait donné deux fois
- Sauter en avant est permis, avec un avertissement (les numéros sautés ne seront jamais donnés)
- Pour une séquence remise à zéro, « First number of each new period » règle le départ des périodes suivantes (1 par défaut)
- Chaque changement est tracé en bas de l'écran : qui, quand, avant, après
- Une fois qu'une séquence a donné des numéros, sa règle de remise à zéro ne bouge plus

## Quand le numéro est donné

Le plugin s'accroche à `wsf_submit_update`, déclenché par l'action « Save Submission » juste après l'enregistrement (priorité 5). À ce moment, la validation et l'anti-spam sont passés, la soumission a son ID et son statut définitif, et les autres actions n'ont pas encore tourné : l'e-mail (priorité 175) et le Hook (190) voient le numéro.

- Un brouillon « sauvegarder et reprendre » ne reçoit rien. Il reçoit son numéro le jour où il est envoyé
- Un envoi refusé par la validation n'est pas enregistré, donc pas numéroté
- Un spam ne reçoit rien. S'il est marqué « pas un spam » dans l'admin, il reçoit son numéro à ce moment-là
- Une soumission payée ou non reçoit son numéro à l'envoi, y compris avec [Paiement Monetico pour WS Form](https://github.com/willybahuaud/wsform-monetico). Pour attendre le paiement, voir plus bas

Un numéro donné ne change plus : une soumission renvoyée, un brouillon repris ou une édition dans l'admin ne le touchent pas. Un numéro supprimé n'est jamais redonné.

Formulaire dupliqué ou importé : s'il utilisait une séquence partagée, il la garde. S'il avait sa propre séquence, il en reçoit une nouvelle, qui repart de 1 (elle est liée à l'ID du champ, qui change à la copie). Un formulaire importé d'un autre site qui pointe vers une séquence partagée inconnue ici prend sa propre séquence, et l'écran des séquences te le signale.

## Numéroter plus tard

Le filtre `wsform_submission_number_assign_now` décide si une soumission reçoit son numéro tout de suite. Renvoie `false` et le champ reste vide. Ensuite, `wsform_submission_number_assign( $submit_id )` donne le numéro depuis n'importe quel autre hook.

Exemple, à mettre dans ton thème ou une extension : les formulaires avec un paiement Monetico ne sont numérotés qu'une fois le paiement confirmé.

```php
add_filter( 'wsform_submission_number_assign_now', 'mysite_wait_for_payment', 10, 2 );
add_action( 'wsform_monetico_payment_updated', 'mysite_number_on_payment', 10, 2 );

/**
 * Les formulaires avec un paiement Monetico attendent le paiement.
 *
 * @param bool            $assign_now Numéroter maintenant ou pas.
 * @param \WS_Form_Submit $submit     Soumission.
 * @return bool
 */
function mysite_wait_for_payment( $assign_now, $submit ) {
	if ( false !== WS_Form_Action::get_form_action_config( $submit->form_object, 'monetico_payment' ) ) {
		return false;
	}

	return $assign_now;
}

/**
 * Donne le numéro quand Monetico confirme le paiement.
 *
 * @param int    $submission_id ID de la soumission WS Form.
 * @param string $new_status    "paid" ou "failed".
 * @return void
 */
function mysite_number_on_payment( $submission_id, $new_status ) {
	if ( 'paid' === $new_status ) {
		wsform_submission_number_assign( $submission_id );
	}
}
```

Attention : l'e-mail envoyé à la soumission part alors sans numéro. Celui de Monetico, envoyé au paiement, peut l'afficher avec `{field:ID}`.

Le reste de l'API :

```php
/**
 * Filtre : numéroter maintenant ou pas.
 *
 * @param bool            $assign_now Par défaut true.
 * @param \WS_Form_Submit $submit     Soumission, avec son formulaire dans $submit->form_object.
 * @param int             $field_id   ID du champ numéro.
 * @param string          $context    "submit" à l'envoi, "status" quand un spam est libéré.
 */
apply_filters( 'wsform_submission_number_assign_now', true, $submit, $field_id, $context );

/**
 * Action : une soumission vient de recevoir son numéro.
 *
 * @param string          $formatted Numéro, par exemple DEV-2026-0042.
 * @param array           $row       submit_id, field_id, form_id, sequence_id, period, number, formatted.
 * @param \WS_Form_Submit $submit    Soumission.
 */
do_action( 'wsform_submission_number_assigned', $formatted, $row, $submit );

wsform_submission_number_assign( $submit_id ); // Donne les numéros manquants, renvoie [ field_id => numéro ].
wsform_submission_number_get( $submit_id );    // Numéro du premier champ, ou '' s'il n'y en a pas.
```

## Pourquoi le compteur ne donne jamais deux fois le même numéro

`get_option()` puis `update_option()` ne suffit pas : deux envois simultanés lisent la même valeur et repartent avec le même numéro. Avec un cache d'objets persistant (Redis, Memcached), c'est pire, la valeur lue peut même être périmée.

Les compteurs ont donc leur propre table, une ligne par séquence et par période, et chaque numéro sort d'une seule requête :

```sql
INSERT INTO …counters (sequence_id, period, value) VALUES (…, …, LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1);
SELECT LAST_INSERT_ID();
```

MySQL verrouille la ligne pendant l'écriture, et `LAST_INSERT_ID(expr)` rend la nouvelle valeur à la connexion qui l'a écrite, et à elle seule. Pas de verrou à gérer, pas de cache. La période fait partie de la clé (`2026`, `2026-11`, ou `all`) : la remise à zéro, c'est simplement la première ligne d'une nouvelle période, sans tâche planifiée.

Le garde-fou du prochain numéro marche pareil : `UPDATE … WHERE value < nouvelle_valeur`, le contrôle et l'écriture sont la même requête. Un envoi qui arrive pendant que tu enregistres ne peut pas créer de doublon.

Une deuxième table garde le numéro de chaque soumission. C'est elle qui fait foi : la valeur de la soumission n'en est qu'une copie.

Testé dans le lab : 50 envois REST simultanés sur le même formulaire donnent les numéros 1 à 50, chacun une fois.

## Les limites

- **Ce n'est pas une numérotation de factures conforme.** En France, une facture doit suivre une séquence continue, chronologique et sans trou. Ce plugin ne le garantit pas, utilise un vrai logiciel de facturation
- **Des trous sont possibles** : une soumission supprimée garde son numéro pour elle, et un envoi qui plante juste après avoir pris son numéro le perd
- **Il faut l'action « Save Submission ».** Sans elle WS Form n'enregistre rien, donc rien n'est numéroté. L'écran des séquences liste les formulaires concernés
- Un champ numéro dans une section répétable est ignoré : une soumission a un numéro, pas un par ligne
- L'édition d'une soumission dans l'admin ne peut pas changer le numéro. Si tu dois vraiment le corriger, c'est en base
- Si tu désactives le plugin, WS Form ne connaît plus le type de champ : la colonne disparaît de la liste des soumissions, mais les valeurs restent en base et reviennent à la réactivation
- La recherche de la liste des soumissions ne marche pas avec le chiffrement des soumissions de WS Form, pour ce champ comme pour les autres

## Confidentialité

Aucun service tiers, aucun traceur. Tout se passe sur ton serveur.

Le plugin interroge l'API publique de GitHub (sans jeton, depuis le serveur) pour savoir s'il existe une nouvelle version. Les réponses sont gardées en cache 12 h, et les échecs 1 h.

## Développer

```bash
composer install
composer lint      # PHPCS, règles WordPress
composer test      # tests unitaires (format, périodes), sans WordPress
composer build     # dist/wsform-submission-number-X.Y.Z.zip, sans les tests ni les outils de dev
```

Les tests d'intégration ont besoin d'un WordPress avec WS Form. Ils tournent sur un site local avec WP-CLI :

```bash
wp eval-file wp-content/plugins/wsform-submission-number/tests/integration/run.php --user=admin
```

Ils créent leurs formulaires, envoient de vraies requêtes HTTP au site (WS Form termine ses réponses REST par un `exit`), cherchent l'e-mail dans Mailpit, puis suppriment tout ce qu'ils ont créé. Pendant le passage, ils copient un petit fichier dans `mu-plugins` qui ne réagit qu'à leur en-tête `X-Lab-Scenario`. L'adresse du site et celle de Mailpit sont réglées pour ddev, en haut de `tests/integration/class-lab-test.php`.

Ne teste pas une mise à jour du plugin sur un site où le dossier du plugin est ton dépôt : la mise à jour le remplacerait par le contenu du zip.

## Mises à jour et versions

Le plugin se met à jour depuis les releases GitHub (en-tête `Update URI`). Ça ne marche que si le dépôt est public : le plugin n'embarque aucun jeton.

Pour publier une version : mets à jour la version dans `wsform-submission-number.php`, `includes/class-plugin.php` et le `Stable tag` de `readme.txt`, puis pousse un tag `vX.Y.Z`. Le workflow refuse un tag qui ne correspond pas, lance PHPCS et les tests unitaires, construit l'archive et publie la release.

Ce mécanisme est interdit sur WordPress.org. Une version publiée là-bas devra le retirer.

## Licence

GPL-2.0-or-later.

Fait par [Willy Bahuaud](https://wabeo.fr).

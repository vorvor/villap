# Villa Promenade Contact

Drupal 11 contact and private customer request management module. Enable with
`drush en villa_promenade_contact -y` and rebuild caches with `drush cr`.
For an existing installation, run `drush updb -y` first. The public form is at
`/contact/event` and is also embedded on the front page.

The form has Name, Email and three Event choices. Drupal Form API states reveal
the matching questions for the selected event:

- event1: PRIVÁT ESEMÉNYEK, https://tally.so/r/gD27Xl.
- event2: CÉGES ESEMÉNYEK, https://tally.so/r/VLgALj.
- other: Egyéb, with an optional event-description textarea.

`EventQuestions` preserves question order, labels, choices and single/multiple
selection rules. Other options reveal a free-text input. Required questions are
marked with Drupal's native required label/legend indicator through preprocess
hooks and validated server-side only for the selected event. Only that
section and selected Other answers are saved; multi-select answers retain every
selected label. Without JavaScript all labelled sections remain available.
Existing event identifiers and legacy event3–event5 requests are preserved.

Security uses Drupal Form API protection plus an off-screen, non-focusable text
honeypot checked on the server. A signed timestamp rejects submissions within
three seconds, expired forms (two hours), and tampered timing tokens. The route
and form are uncached so each page load receives a fresh token. This is local
honeypot protection, not a dependency on the contributed Honeypot module, and
is not a guarantee against sophisticated bots.

Successful submissions save a private `villa_customer_request` content entity
with customer name, email, selected event details, submission time and New status.
When staff notifications are configured and enabled, each recipient receives
an email after the request is saved. Honeypot values and timing tokens are not saved. Submitted
personal data is not included in module log messages.

## Administration

Open `/admin/content/customer-requests` or Content > Customer requests.
This is a real Drupal View, editable at
`/admin/structure/views/view/villa_customer_requests` by users with Views permissions.
The table includes request ID, customer, email, event, status, demo marker,
submitted date and edit/delete actions. Customer names link to full details.
Filter by name, email, event, status, demo marker or submitted-on-or-after date.
Click sortable table headings to change ordering; newest requests appear first.
The list is paged at 25 requests per page.

Grant `Manage Villa Promenade customer requests` only to trusted staff. Existing
administrator roles receive it automatically. The list, entity details, editing
and deletion are permission protected; requests are not public nodes.
Managers can edit contact details, event details, request status and internal notes.
Statuses are New, In progress, Completed and Cancelled. Deletion uses a
confirmation form and permanently removes the request.

## Email notifications

Open `/admin/config/system/villa-promenade-contact` or Configuration > System >
Villa Promenade contact notifications. Grant `Administer Villa Promenade contact
notification settings` only to trusted administrators. Enable notifications and
enter the staff email addresses, one per line or separated by commas/semicolons.
Invalid addresses are rejected, duplicates removed, and at least one address is
required when enabled. Notifications are disabled with no recipients by default.

Each recipient receives a separate message containing the request ID, customer
name/email, event, submission time, selected event details and a staff-only link.
The From address comes from Drupal's site email settings; Reply-To is the
customer's validated email. Internal notes, inactive event fields, honeypot data
and timing tokens are not mailed. Demo imports and admin edits do not notify.

The Drupal Mail API uses the site's configured mail backend. In this Lando setup,
outgoing web mail is captured by MailHog. Production needs a working mail backend
and sender configuration. A backend acceptance result is not a guarantee of
delivery to a recipient's inbox.

Mail failure never removes or rolls back the stored customer request. The module
logs a failure with the request ID; no automatic retries are configured. Settings
are installed on existing sites by `drush updb -y`.

## Demo data

Run `drush php:script web/modules/custom/villa_promenade_contact/scripts/create-demo-requests.php`
to add ten fictional requests covering all five events and four statuses.
Names contain Demo, addresses use `example.com`, and every fixture has its Demo
marker set. The script skips existing fixtures instead of overwriting them.
Use the Demo requests filter to find and delete these records before launch.

Before launch, finalize the event fields, retention policy and privacy copy,
staff permissions, notification recipients and production mail delivery. Uninstalling this module removes
its request storage and data; back up required records first.

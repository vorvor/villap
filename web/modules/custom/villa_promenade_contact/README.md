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
Only event1, event2 and other are allowed in request storage, forms and filters.

Security uses Drupal Form API protection plus an off-screen, non-focusable text
honeypot checked on the server. A signed timestamp rejects submissions within
three seconds, expired forms (two hours), and tampered timing tokens. The route
and form are uncached so each page load receives a fresh token. This is local
honeypot protection, not a dependency on the contributed Honeypot module, and
is not a guarantee against sophisticated bots.

Successful submissions save an unpublished node of the `customer_request` content type
with customer name, email, selected event details, submission time and New status.
When staff notifications are configured and enabled, each recipient receives
an email after the request is saved. Honeypot values and timing tokens are not saved. Submitted
personal data is not included in module log messages.

## Administration

Open `/admin/content/customer-requests` or Dashboard > Customer requests.
This is a real Drupal View, editable at
`/admin/structure/views/view/villa_customer_requests` by users with Views permissions.
The tiles include request ID, customer, email, event, demo marker,
submitted date and edit/delete actions. Customer names link to full details.
The New, Cancelled, Archive and Approved tabs open separate status-specific pages:
the main URL shows New; `/cancelled`, `/archive` and `/approved` show the other states.
Use Approve on a tile to save its Approved status and move it to the Approved tab.
Filter each page by name, email, event, demo marker or submitted-on-or-after date.
Changing a tile's status refreshes the current list through AJAX and moves the
request to its matching tab. Legacy In progress/Completed requests remain stored
and editable but do not appear in these status-specific pages.
Requests default to planned event date ascending, with undated requests last
and request ID ascending as a stable tie-breaker. The internal date key is kept
in sync whenever request details are saved; original answers remain unchanged.
The list is paged at 25 requests per page.

Grant `Manage Villa Promenade customer requests` only to trusted staff. Existing
administrator roles receive it automatically. The list, entity details, editing
and deletion are permission protected. Requests remain unpublished, with private
node grants and field-access protection; they are not public website content.
Managers can edit contact details, event details, request status and internal notes.
Statuses are New, Approved, In progress, Completed, Cancelled and Archive. Each tile has
Approve, Cancelled, Archive and New buttons with AJAX saving, permission checks and
Form API CSRF protection. The current state is shown and its button disabled.
Archiving retains the request and its data. Deletion uses a
confirmation form and permanently removes the request.

### Content type structure

Manage the fields at `/admin/structure/types/manage/customer_request/fields`,
the form at `/admin/structure/types/manage/customer_request/form-display`, and
the display at `/admin/structure/types/manage/customer_request/display`.
Customer name is the node title. Email, event, request status, internal notes,
Real date, Approve note and the demo marker remain configurable fields.
Questions with the same meaning share a configurable field across event types
(`cr_guest_count`, `cr_requests`, `cr_technical_other`, etc.). Event-specific
questions also use the `cr_*` prefix without duplicating storage per event.
Native date, integer, plain-text and list fields retain the public question
labels and single/multiple selection rules. Shared lists combine the choices
from each event, using label-based storage keys to prevent conflicting public
option identifiers from changing their meaning. The node edit form shows the
selected event's questions with server-side conditional required validation.
Public submissions write directly to these fields; inactive sections and
unselected Other answers are discarded. No existing dummy answers are migrated.
The old `details` field is hidden from node editing/display and generated from
the structured answers for existing request tiles and email notifications.
The planned-date sort key is derived from the selected event's date field and
remains internal and hidden from editing.
Additional fields added through Drupal UI appear according to form/display
configuration; the public conditional event form remains a dedicated form.

The refactor installs structure only: no legacy content is copied and no legacy
URL compatibility layer is used. Existing legacy demo rows remain untouched in
retired storage with no management routes; the new node-based list starts empty.
Active forms, notifications, tile actions, Views and bulk deletion all use nodes.
The legacy entity definition exists only to retain the old database schema,
not as an alternative active customer request system.

## Customer request calendar

The final Customer requests tab opens `/admin/content/customer-requests/calendar`.
Its dedicated View includes only New and Approved requests. New requests use
`cr_preferred_date`; Approved requests use `real_date`, falling back to
`cr_preferred_date` if no confirmed date is recorded. Each day shows the customer
title, event type and `cr_guest_count`, using native access-checked formatters.
Undated requests appear below the calendar rather than on an invented date.
Month navigation and the month picker use the `month=YYYY-MM` query parameter;
the initial month is the current month. The native Views calendar style limits
the database query to the chosen month and undated requests, with no pagination.
Calendar access uses the same staff permission as the existing request lists.

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
to add ten fictional requests covering the three current event types, all starting as New.
Names contain Demo, addresses use `example.com`, and every fixture has its Demo
marker set. The script skips existing fixtures instead of overwriting them.
Use the Demo requests filter to find and delete these records before launch.

Before launch, finalize the event fields, retention policy and privacy copy,
staff permissions, notification recipients and production mail delivery. Archive or
remove private customer request nodes before uninstalling this module: it provides
their privacy enforcement and submission/management integration.

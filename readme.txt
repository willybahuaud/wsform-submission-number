=== Submission Numbers for WS Form ===
Contributors: willybahuaud
Tags: ws form, submission, number, sequence, counter
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Readable, continuous numbers for WS Form submissions, such as DEV-2026-0042, per form or shared between forms.

== Description ==

WS Form gives each submission a #submit_id, but it is the database row ID: shared by all forms, and also used by "save and resume" drafts. A quote form jumps from 112 to 157 for no visible reason.

This plugin adds a field that receives a number of its own, per form or shared by several forms, for orders, quotes, tickets, files or registrations. It works with WS Form LITE and PRO.

= Submission Number field =

* Hidden from visitors, filled in by the server when the submission is saved
* A value sent by the browser is ignored
* A real field: the number shows in the submission list, the CSV export, the search, #field(ID), #email_submission and post mapping
* The #submission_number variable returns the number of the first such field

= Sequences, in WS Form > Numbering =

* A field gets its own sequence when the form is published, or uses a shared one
* Format with tokens: {N}, {N:6}, {YYYY}, {YY}, {MM}, {DD}, for example DEV-{YYYY}-{N}
* Padding, reset (never, yearly, monthly) and next number, with a live preview
* Continue from another tool's numbering: the next number can jump forward, never go back to a number already given
* Every next number change is logged

= When a number is given =

* Only to saved, sent submissions that passed validation and spam checks
* Before the other actions run, so emails show it
* Drafts get their number when sent, spam when marked as not spam
* A given number never changes, and is never given again

= Known limits =

* This is not a compliant invoice numbering: gaps are possible (deleted submissions, a request failing after taking its number)
* The form needs a "Save Submission" action
* Number fields in repeatable sections are ignored

== Installation ==

1. Download wsform-submission-number-X.Y.Z.zip from the latest GitHub release.
2. Install it next to WS Form, LITE or PRO.
3. Add a "Submission Number" field to a form, then publish the form.

== Developers ==

The `wsform_submission_number_assign_now` filter can delay numbering, and `wsform_submission_number_assign( $submit_id )` numbers a submission later, for example once a payment is confirmed. The README on GitHub has a full example.

== Privacy ==

No third-party service, no tracking. The plugin only asks the public GitHub API, from the server and without any token, whether a new version exists.

== Updates ==

Updates come from the GitHub releases of willybahuaud/wsform-submission-number.

== Changelog ==

= 0.1.0 =
* Feature: Submission Number field, sequences per form or shared, token format, yearly or monthly reset.
* Feature: sequences screen with live preview, guarded next number and change log.
* Feature: filter and function to number a submission later.

# Reports + SMTP Reply-To TODO

## Reports Upgrade
- [x] Add routes: `reports.warmup`, `reports.export`
- [x] Add controller methods: `warmupReport()`, `export()`
- [x] Add Warmup tab + Export CSV to campaign report view
- [x] Add Warmup tab + Export CSV to single email report view
- [x] Create warmup report view
- [x] Add Warmup Report to sidebar

## SMTP Reply-To Upgrade
- [ ] Add DB migration for `smtp_servers.reply_to_name` and `smtp_servers.reply_to_email`
- [ ] Update `SmtpServer` model fillable
- [ ] Update `SMTPController` validation/store/update for reply-to fields
- [ ] Update SMTP bulk upload to accept optional `reply_to_name`, `reply_to_email`
- [ ] Update SMTP test mail to include `replyTo(...)` when provided
- [ ] Update SMTP runtime mail config to include `mail.reply_to.*`
- [ ] Update SMTP index view (create form + list + CSV helper text)
- [ ] Update SMTP edit view
- [ ] Verify campaign/single/drip sending paths apply reply-to consistently

## Validation / Testing
- [ ] Run migration
- [ ] PHP syntax checks for touched PHP files
- [ ] Functional check: create/edit SMTP with reply-to
- [ ] Functional check: send test email with reply-to
- [ ] Functional check: CSV upload with/without reply-to columns

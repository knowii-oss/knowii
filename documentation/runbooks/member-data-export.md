# Member data export

Use this runbook when a member asks for a copy of their data. The privacy policy promises it (section 8, GDPR art. 12(3), 15 and 20): the copy comes in a machine-readable format, within one month. The member writes to contact@developassion.be. A self-service button does not exist yet.

The export is a copy of personal data: the account, the profile, the messages, the files. Handle it with care.

## Steps

1. Note the day you received the request. The answer is due within one month of that day.
2. Check that the request comes from the email address of the account. Compare the sender address with the `email` of the account in the database or the admin tools. If the address differs, do not export. Ask the member to write again from the address of the account. If the member cannot, ask for another proof of identity that you can check, and never send the data to a new address.
3. Run the command on the server, in the application container:

   ```bash
   php artisan users:export member@example.com
   ```

   The command prints the absolute path of one zip file, under `exports/` of the `local` disk: `user-<username>-<YYYYMMDD-HHMMSS>.zip`. If the address is unknown, the command says so and writes nothing.

4. Open the zip file and read `README.txt`. Check that the files you expect are there. Do not read the content for other reasons.
5. Send the file to the member through a link that expires (for example a file sharing link that ends after a few days, with a password sent in another message). Send it only to the address of the account. Never attach the file to an email.
6. Tell the member what the file holds and what it leaves out. It holds the notifications about them (kind, community, identifiers, dates) without any text, and the accounts of other providers that they connected to sign in (the provider, their identifier there and the dates). It never holds what other members wrote, nor the password hash, the two-factor secrets, the value of the API tokens or the session data. The `README.txt` in the file says the same.
7. The file stays on the server for `RETENTION_EXPORTS_DAYS` days (7 by default). Then `retention:prune` deletes it. If the member confirms the download earlier, delete the file yourself.
8. Record the request: the day you received it, how you checked the identity, the day you answered. Do not record the content of the export.

## Check

1. `php artisan retention:prune --dry-run` shows the number of export files that are due for deletion in the `exports` line.
2. The log has one line `User exported` with the account identifier (cuid). It never holds the content.
